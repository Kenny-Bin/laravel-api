<?php

namespace App\Services\V1;

use App\Models\Admin\AdminMember;
use App\Models\Admin\BoardNotice;
use App\Models\Admin\BoardNoticeFile;
use App\Services\BaseService;
use App\Services\Contracts\FileUploadServiceInterface;
use App\Services\Contracts\NoticeServiceInterface;
use App\Traits\HasPaginationResponse;

class NoticeService extends BaseService implements NoticeServiceInterface
{
    use HasPaginationResponse;

    protected FileUploadServiceInterface $fileUploadService;

    public function __construct(FileUploadServiceInterface $fileUploadService)
    {
        parent::__construct();
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * 공지사항 목록 조회 (페이징)
     *
     * @param  int  $page  페이지 번호
     * @param  int  $perPage  페이지당 항목 수
     * @param  array  $filters  검색 필터 (search, status 등)
     */
    public function getNoticeList(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $query = BoardNotice::where('is_active', true);
        // 검색 필터 적용 (JSONB 필드 검색)
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereRaw('subject_language::text ILIKE ?', ["%{$search}%"])
                    ->orWhereRaw('content_language::text ILIKE ?', ["%{$search}%"]);
            });
        }

        // 날짜 범위 필터 (create_ts, 한국시간 기준)
        if (! empty($filters['date_from'])) {
            $query->whereRaw("DATE(create_ts AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Seoul') >= ?", [$filters['date_from']]);
        }

        if (! empty($filters['date_to'])) {
            $query->whereRaw("DATE(create_ts AT TIME ZONE 'UTC' AT TIME ZONE 'Asia/Seoul') <= ?", [$filters['date_to']]);
        }

        if (isset($filters['is_view'])) {
            $query->where('is_view', (int) $filters['is_view']);
        }

        if (isset($filters['is_top'])) {
            $query->where('is_top', (int) $filters['is_top']);
        }

        // 전체 개수 조회
        $total = $query->count();

        // 페이징 적용
        $items = $query->orderBy('create_ts', 'desc')
            ->orderBy('board_notice_seq', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()->toArray();

        return $this->buildPaginationResponse($items, $total, $page, $perPage);
    }

    /**
     * 공지사항 상세 조회
     *
     * @param  int  $board_notice_seq  공지사항 번호
     *
     * @throws \Exception
     */
    public function getNoticeDetail(int $board_notice_seq): array
    {
        $notice = BoardNotice::with('files')
            ->where('board_notice_seq', $board_notice_seq)
            ->where('is_active', true)
            ->first();

        if (! $notice) {
            $this->log->error('공지사항 상세 조회 실패', [
                'board_notice_seq' => $board_notice_seq,
                'reason' => 'NOTICE_DETAIL_NULL',
            ]);
            throw new \Exception(json_encode([
                'code' => 'NOTICE_DETAIL_NULL',
                'message' => '',
            ]));
        }

        $noticeArray = $notice->toArray();

        // 첨부파일 정보를 attachment_language 형식으로 변환
        if (! empty($notice->files) && $notice->files->count() > 0) {
            $file = $notice->files->first();
            $noticeArray['attachment_language'] = [
                'names' => $file->json_file_name ?? [],
                'paths' => $file->json_file_path ?? [],
            ];
        } else {
            $noticeArray['attachment_language'] = [
                'names' => [],
                'paths' => [],
            ];
        }

        return $noticeArray;
    }

    /**
     * 공지사항 생성
     *
     * @param  array  $data  공지사항 데이터
     * @param  int  $adminSeq  관리자 ID
     *
     * @throws \Exception
     */
    public function createNotice(array $data): array
    {
        return $this->executeInTransaction(function () use ($data) {

            $adminSeq = request()->attributes->get('admin_seq');

            // 관리자 이름 조회
            $admin = AdminMember::find($adminSeq);
            $adminName = $admin ? $admin->adm_name : '관리자';

            $notice = BoardNotice::create([
                'subject_language' => $data['subject_language'] ?? [],
                'content_language' => $data['content_language'] ?? [],
                'is_top' => (bool) ($data['is_top'] ?? false),
                'is_view' => $data['is_view'] ?? true,
                'writer_name' => $adminName,
                'is_active' => true,
            ]);

            // 파일 업로드 처리
            if (! empty($data['attachment'])) {
                $this->handleFileUpload($notice->board_notice_seq, $data['attachment']);
            }

            return $notice->fresh()->toArray();
        });
    }

    /**
     * 공지사항 수정
     *
     * @param  int  $board_notice_seq  공지사항 번호
     * @param  array  $data  공지사항 데이터
     *
     * @throws \Exception
     */
    public function updateNotice(int $board_notice_seq, array $data): array
    {
        return $this->executeInTransaction(function () use ($board_notice_seq, $data) {

            $notice = BoardNotice::where('board_notice_seq', $board_notice_seq)
                ->where('is_active', true)
                ->first();

            if (! $notice) {
                $this->log->error('공지사항 수정 실패 - 공지사항을 찾을 수 없음', [
                    'board_notice_seq' => $board_notice_seq,
                    'reason' => 'NOTICE_DETAIL_NULL',
                ]);
                throw new \Exception(json_encode([
                    'code' => 'NOTICE_DETAIL_NULL',
                    'message' => '',
                ]));
            }

            $notice->update([
                'subject_language' => $data['subject_language'] ?? [],
                'content_language' => $data['content_language'] ?? [],
                'is_top' => (bool) ($data['is_top'] ?? false),
                'is_view' => $data['is_view'] ?? true,
            ]);

            // 파일 업로드 처리 (기존 파일과 새 파일 병합 + 삭제 처리)
            $hasFileChanges = ! empty($data['attachment']) || ! empty($data['deleted_attachment_languages']);

            if ($hasFileChanges) {
                // 기존 파일 데이터 가져오기
                $existingFile = BoardNoticeFile::where('board_notice_seq', $board_notice_seq)
                    ->where('is_active', true)
                    ->first();

                $existingNames = $existingFile ? ($existingFile->json_file_name ?? []) : [];
                $existingPaths = $existingFile ? ($existingFile->json_file_path ?? []) : [];

                // 삭제할 언어 처리
                $deletedLanguages = $data['deleted_attachment_languages'] ?? [];
                foreach ($deletedLanguages as $lang) {
                    $langCode = strtoupper($lang); // 대문자로 통일

                    // 실제 파일 삭제 (로컬 + S3)
                    if (isset($existingPaths[$langCode])) {
                        $dbPath = $existingPaths[$langCode]; // "/notice/202501/파일명.png"
                        $relativePath = ltrim($dbPath, '/'); // "notice/202501/파일명.png"

                        try {
                            // 로컬과 S3 모두 같은 경로 사용
                            $this->fileUploadService->deleteFile($relativePath, $relativePath);
                        } catch (\Exception $e) {
                            $this->log->error('파일 삭제 실패', [
                                'board_notice_seq' => $board_notice_seq,
                                'language' => $langCode,
                                'path' => $relativePath,
                                'error' => $e->getMessage(),
                            ]);
                            throw $e;
                        }
                    }

                    unset($existingNames[$langCode]);
                    unset($existingPaths[$langCode]);
                }

                // 새 파일 업로드 및 기존 데이터와 병합
                if (! empty($data['attachment'])) {
                    $mergedData = $this->handleFileUploadForUpdate($board_notice_seq, $data['attachment'], $existingNames, $existingPaths);
                } else {
                    $mergedData = [
                        'names' => $existingNames,
                        'paths' => $existingPaths,
                    ];
                }

                // 기존 레코드가 있으면 업데이트, 없으면 생성
                if ($existingFile) {
                    if (empty($mergedData['names']) && empty($mergedData['paths'])) {
                        // 모든 파일이 삭제되면 레코드 비활성화
                        $existingFile->update(['is_active' => false]);
                    } else {
                        $existingFile->update([
                            'json_file_name' => $mergedData['names'],
                            'json_file_path' => $mergedData['paths'],
                        ]);
                    }
                } elseif (! empty($mergedData['names'])) {
                    BoardNoticeFile::create([
                        'board_notice_seq' => $board_notice_seq,
                        'json_file_name' => $mergedData['names'],
                        'json_file_path' => $mergedData['paths'],
                        'is_active' => true,
                    ]);
                }
            }

            return $notice->fresh()->toArray();
        });
    }

    /**
     * 공지사항 삭제
     *
     * @param  int  $board_notice_seq  공지사항 번호
     *
     * @throws \Exception
     */
    public function deleteNotice(int $board_notice_seq): void
    {
        $this->executeInTransaction(function () use ($board_notice_seq) {

            $notice = BoardNotice::where('board_notice_seq', $board_notice_seq)
                ->where('is_active', true)
                ->first();

            if (! $notice) {
                $this->log->error('공지사항 삭제 실패 - 공지사항을 찾을 수 없음', [
                    'board_notice_seq' => $board_notice_seq,
                ]);
                throw new \Exception('공지사항을 찾을 수 없습니다.');
            }

            // soft delete (is_active = false)
            $notice->update(['is_active' => false]);

            // 첨부파일도 비활성화
            BoardNoticeFile::where('board_notice_seq', $board_notice_seq)
                ->update(['is_active' => false]);
        });
    }

    /**
     * 다국어 파일 업로드 처리 (생성용)
     *
     * @param  int  $boardNoticeSeq  공지사항 번호
     * @param  array  $files  언어별 파일 배열 (예: ['ko' => UploadedFile, 'EN' => UploadedFile])
     */
    private function handleFileUpload(int $boardNoticeSeq, array $files): void
    {
        $fileNames = [];
        $filePaths = [];

        foreach ($files as $lang => $file) {
            if ($file && $file->isValid()) {
                try {
                    // FileUploadService를 사용하여 파일 업로드
                    $uploadResult = $this->fileUploadService->uploadFile($file, 'notice');

                    // JSON 데이터 구성 (언어 코드는 대문자로 통일)
                    $langCode = strtoupper($lang);
                    $fileNames[$langCode] = $uploadResult['original_filename'];  // 원본 파일명
                    $filePaths[$langCode] = $uploadResult['s3_path'];  // S3 경로 (도메인 제외)
                } catch (\Exception $e) {
                    $this->log->error('파일 업로드 실패', [
                        'board_notice_seq' => $boardNoticeSeq,
                        'language' => strtoupper($lang),
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        }

        // 파일이 하나라도 있으면 DB에 저장
        if (! empty($fileNames)) {
            BoardNoticeFile::create([
                'board_notice_seq' => $boardNoticeSeq,
                'json_file_name' => $fileNames,
                'json_file_path' => $filePaths,
                'is_active' => true,
            ]);
        }
    }

    /**
     * 다국어 파일 업로드 처리 (수정용 - 기존 파일과 병합)
     *
     * @param  int  $boardNoticeSeq  공지사항 번호
     * @param  array  $files  언어별 파일 배열
     * @param  array  $existingNames  기존 파일명 JSON 데이터
     * @param  array  $existingPaths  기존 파일 경로 JSON 데이터
     * @return array ['names' => [...], 'paths' => [...]]
     */
    private function handleFileUploadForUpdate(int $boardNoticeSeq, array $files, array $existingNames, array $existingPaths): array
    {
        // 기존 데이터로 시작
        $mergedNames = $existingNames;
        $mergedPaths = $existingPaths;

        foreach ($files as $lang => $file) {
            if ($file && $file->isValid()) {
                $langCode = strtoupper($lang);

                try {
                    // 기존 파일이 있으면 삭제 (교체)
                    if (isset($existingPaths[$langCode])) {
                        $dbPath = $existingPaths[$langCode]; // "/notice/202501/파일명.png"
                        $relativePath = ltrim($dbPath, '/'); // "notice/202501/파일명.png"

                        // 로컬과 S3 모두 같은 경로 사용
                        $this->fileUploadService->deleteFile($relativePath, $relativePath);
                    }

                    // FileUploadService를 사용하여 새 파일 업로드
                    $uploadResult = $this->fileUploadService->uploadFile($file, 'notice');

                    // 새 파일 정보로 덮어쓰기
                    $mergedNames[$langCode] = $uploadResult['original_filename'];
                    $mergedPaths[$langCode] = $uploadResult['s3_path'];
                } catch (\Exception $e) {
                    $this->log->error('파일 업로드/교체 실패', [
                        'board_notice_seq' => $boardNoticeSeq,
                        'language' => $langCode,
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        }

        return [
            'names' => $mergedNames,
            'paths' => $mergedPaths,
        ];
    }
}
