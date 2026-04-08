<?php

namespace App\Services\V1;

use App\Services\BaseService;
use App\Services\Contracts\FileUploadServiceInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileUploadService extends BaseService implements FileUploadServiceInterface
{
    /**
     * 파일을 로컬 및 S3에 업로드하고 메타데이터 반환
     *
     * @param UploadedFile $file 업로드할 파일
     * @param string|null $directory S3 및 로컬 저장 디렉토리 (선택사항)
     * @param array $options 추가 옵션
     * @return array 업로드 결과 메타데이터
     * @throws \Exception 업로드 실패 시
     */
    public function uploadFile(UploadedFile $file, ?string $directory = null, array $options = []): array
    {
        $localPath = null;
        $s3Path = null;

        try {
            // 파일 유효성 검증
            $this->validateFile($file, $options);

            // 고유한 파일명 생성
            $tag = $options['tag'] ?? null;
            $filename = $this->generateUniqueFilename($file, $tag);

            // 년월 디렉토리 생성 (YYYYMM)
            $yearMonth = now()->format('Ym');
            $fullDirectory = $directory ? "{$directory}/{$yearMonth}" : $yearMonth;

            // 이미지 리사이징 처리
            $processedFile = $this->resizeImage($file, $options);

            // 1. 로컬 저장
            $localDisk = $options['local_disk'] ?? 'local';
            $localPath = Storage::disk($localDisk)->putFileAs(
                $fullDirectory,
                $processedFile ?? $file,
                $filename
            );

            if (!$localPath) {
                $this->log->error("파일 로컬 저장 실패: {$file->getClientOriginalName()} → {$fullDirectory}/{$filename}");
                throw new \Exception('로컬 파일 저장 실패');
            }

            // 2. S3 업로드
            $s3Disk = $options['s3_disk'] ?? 's3';

            try {
                // ACL이 비활성화된 버킷에서는 visibility 옵션 제거
                // 버킷 정책이나 IAM으로 public 접근 관리
                $s3Path = Storage::disk($s3Disk)->putFileAs(
                    $fullDirectory,
                    $processedFile ?? $file,
                    $filename
                );

                if (!$s3Path) {
                    $this->log->error("파일 S3 업로드 실패", [
                        'filename' => $file->getClientOriginalName(),
                        'directory' => $fullDirectory,
                        'stored_filename' => $filename,
                    ]);
                    throw new \Exception('S3 파일 업로드 실패');
                }
            } catch (\Aws\S3\Exception\S3Exception $e) {
                // AWS S3 특정 에러
                $this->log->error("AWS S3 에러 발생", [
                    'filename' => $file->getClientOriginalName(),
                    'directory' => $fullDirectory,
                    'aws_error_code' => $e->getAwsErrorCode(),
                    'aws_error_message' => $e->getAwsErrorMessage(),
                ]);

                throw new \Exception('S3 업로드 실패: ' . $e->getAwsErrorMessage(), 0, $e);
            } catch (\Exception $e) {
                $this->log->error("파일 S3 업로드 실패", [
                    'filename' => $file->getClientOriginalName(),
                    'directory' => $fullDirectory,
                    'error' => $e->getMessage(),
                ]);
                throw new \Exception('S3 파일 업로드 실패: ' . $e->getMessage(), 0, $e);
            }

            $s3Path = '/' . ltrim($s3Path, '/');

            // 임시 파일 정리 (리사이징한 경우)
            if ($processedFile && $processedFile !== $file) {
                @unlink($processedFile->getRealPath());
            }

            // 3. S3 URL 생성
            $s3Url = Storage::disk($s3Disk)->url($s3Path);

            // 4. 메타데이터 반환
            $metadata = [
                'original_filename' => $file->getClientOriginalName(),
                'stored_filename' => $filename,
                'local_path' => $localPath,
                'local_full_path' => Storage::disk($localDisk)->path($localPath),
                's3_path' => $s3Path,
                's3_url' => $s3Url,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $file->getClientOriginalExtension(),
                'uploaded_at' => now()->toIso8601String(),
                'resized' => $processedFile !== null,
            ];

            return $metadata;

        } catch (\Exception $e) {
            $this->log->error("파일 업로드 실패: " . $e->getMessage());

            // 실패 시 롤백: 저장된 파일 삭제
            $this->rollbackUpload($localPath, $s3Path, $options);

            throw new \Exception("파일 업로드 실패: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * 여러 파일을 일괄 업로드
     *
     * @param array $files UploadedFile 배열
     * @param string|null $directory 저장 디렉토리
     * @param array $options 추가 옵션
     * @return array 업로드된 파일들의 메타데이터 배열
     */
    public function uploadMultipleFiles(array $files, ?string $directory = null, array $options = []): array
    {
        $uploadedFiles = [];
        $failedFiles = [];

        try {
            foreach ($files as $index => $file) {
                if (!$file instanceof UploadedFile) {
                    $this->log->warning("유효하지 않은 파일 타입 (인덱스: $index)");
                    continue;
                }

                try {
                    $uploadedFiles[] = $this->uploadFile($file, $directory, $options);
                } catch (\Exception $e) {
                    $failedFiles[] = [
                        'index' => $index,
                        'filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                    ];

                    // 하나라도 실패하면 전체 롤백 (옵션에 따라 처리)
                    if ($options['stop_on_error'] ?? false) {
                        throw new \Exception("파일 업로드 실패로 인한 전체 롤백", 0, $e);
                    }
                }
            }

            // 실패한 파일이 있으면 경고 로그
            if (!empty($failedFiles)) {
                $this->log->warning("일부 파일 업로드 실패", ['failed_files' => $failedFiles]);
            }

            return [
                'success' => $uploadedFiles,
                'failed' => $failedFiles,
                'total' => count($files),
                'success_count' => count($uploadedFiles),
                'failed_count' => count($failedFiles),
            ];

        } catch (\Exception $e) {
            // 전체 롤백: 이미 업로드된 파일들 삭제
            foreach ($uploadedFiles as $uploaded) {
                $this->deleteFile($uploaded['local_path'], $uploaded['s3_path']);
            }

            throw $e;
        }
    }

    /**
     * 로컬 및 S3에서 파일 삭제
     *
     * @param string|null $localPath 로컬 파일 경로 (nullable)
     * @param string|null $s3Path S3 파일 경로 (nullable)
     * @return bool 삭제 성공 여부
     */
    public function deleteFile(?string $localPath, ?string $s3Path): bool
    {
        $localDeleted = true; // 기본값 true (삭제할 파일이 없으면 성공으로 간주)
        $s3Deleted = true;

        try {
            // 로컬 파일 삭제
            if ($localPath && Storage::disk('local')->exists($localPath)) {
                $localDeleted = Storage::disk('local')->delete($localPath);
//                $this->log->info("로컬 파일 삭제: $localPath");
            }

            // S3 파일 삭제
            if ($s3Path && Storage::disk('s3')->exists($s3Path)) {
                $s3Deleted = Storage::disk('s3')->delete($s3Path);
//                $this->log->info("S3 파일 삭제: $s3Path");
            }

            return $localDeleted && $s3Deleted;

        } catch (\Exception $e) {
            $this->log->error("파일 삭제 실패: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 파일 유효성 검증
     *
     * @param UploadedFile $file
     * @param array $options
     * @throws \Exception
     */
    protected function validateFile(UploadedFile $file, array $options): void
    {
        // 파일 크기 검증
        if (isset($options['max_size'])) {
            $maxSize = $options['max_size'];
            if ($file->getSize() > $maxSize) {
                throw new \Exception("파일 크기가 제한을 초과했습니다. (최대: " . ($maxSize / 1024 / 1024) . "MB)");
            }
        }

        // 파일 확장자 검증
        if (isset($options['allowed_extensions'])) {
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = array_map('strtolower', $options['allowed_extensions']);

            if (!in_array($extension, $allowedExtensions)) {
                throw new \Exception("허용되지 않은 파일 확장자입니다. 허용: " . implode(', ', $allowedExtensions));
            }
        }

        // MIME 타입 검증
        if (isset($options['allowed_mime_types'])) {
            $mimeType = $file->getMimeType();
            if (!in_array($mimeType, $options['allowed_mime_types'])) {
                throw new \Exception("허용되지 않은 파일 타입입니다.");
            }
        }

        // 파일이 실제로 업로드되었는지 확인
        if (!$file->isValid()) {
            throw new \Exception("유효하지 않은 파일입니다.");
        }
    }

    /**
     * 고유한 파일명 생성
     *
     * @param UploadedFile $file
     * @param string|null $tag 이미지 태그 (선택사항)
     * @return string
     */
    protected function generateUniqueFilename(UploadedFile $file, ?string $tag = null): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('YmdHis');
        $random = Str::random(8);

        if ($tag) {
            return "{$timestamp}_{$random}_{$tag}.{$extension}";
        }

        return "{$timestamp}_{$random}.{$extension}";
    }

    /**
     * 업로드 실패 시 롤백
     *
     * @param string|null $localPath
     * @param string|null $s3Path
     * @param array $options
     */
    protected function rollbackUpload(?string $localPath, ?string $s3Path, array $options): void
    {
        try {
            $localDisk = $options['local_disk'] ?? 'local';
            $s3Disk = $options['s3_disk'] ?? 's3';

            // 로컬 파일 정리
            if ($localPath && Storage::disk($localDisk)->exists($localPath)) {
                Storage::disk($localDisk)->delete($localPath);
//                $this->log->info("롤백: 로컬 파일 삭제 - $localPath");
            }

            // S3 파일 정리
            if ($s3Path && Storage::disk($s3Disk)->exists($s3Path)) {
                Storage::disk($s3Disk)->delete($s3Path);
//                $this->log->info("롤백: S3 파일 삭제 - $s3Path");
            }
        } catch (\Exception $e) {
            $this->log->error("롤백 중 오류 발생: " . $e->getMessage());
        }
    }

    /**
     * 이미지 리사이징 처리
     * - isResize가 true이고 이미지 파일일 경우만 처리
     * - resize_width는 필수
     * - width만 있을 때: 가로 기준 비율 유지
     * - width+height 있을 때:
     *   원본 세로 < 타겟 세로 → 가로 기준 비율 유지
     *   원본 가로 < 타겟 가로 → 세로 기준 비율 유지
     *   둘 다 충분히 크면 → width, height에 맞춰 리사이징
     *
     * @param UploadedFile $file
     * @param array $options
     * @return UploadedFile|null
     */
    protected function resizeImage(UploadedFile $file, array $options): ?UploadedFile
    {
        // 리사이징 옵션이 false이면 스킵 (기본값: true)
        if (($options['isResize'] ?? true) === false) {
            return null;
        }

        // 이미지가 아니면 스킵
        if (!$this->isImage($file)) {
            return null;
        }

        // width는 필수
        if (!isset($options['width'])) {
            return null;
        }

        try {
            // ImageManager 인스턴스 생성
            $manager = new ImageManager(new Driver());
            $img = $manager->read($file->getRealPath());

            $targetWidth = (int) $options['width'];
            $targetHeight = isset($options['height']) ? (int) $options['height'] : null;
            $originalWidth = $img->width();
            $originalHeight = $img->height();

            // 리사이징 처리
            if ($targetHeight === null) {
                // 1. width만 있는 경우: 가로 기준 비율 유지
                $img->scale(width: $targetWidth);

//                $this->log->info("이미지 리사이징 (가로 기준)", [
//                    'original' => "{$originalWidth}x{$originalHeight}",
//                    'target' => "{$targetWidth}x?",
//                ]);
            } else {
                // 2. width + height 모두 있는 경우
                if ($originalHeight < $targetHeight) {
                    // 원본 세로가 타겟보다 작음 → 크롭
                    $img->cover($targetWidth, $targetHeight);

//                    $this->log->info("이미지 리사이징 (원본 세로 부족 → 크롭)", [
//                        'original' => "{$originalWidth}x{$originalHeight}",
//                        'target' => "{$targetWidth}x{$targetHeight}",
//                    ]);
                } elseif ($originalWidth < $targetWidth) {
                    // 원본 가로가 타겟보다 작음 → 크롭
                    $img->cover($targetWidth, $targetHeight);

//                    $this->log->info("이미지 리사이징 (원본 가로 부족 → 크롭)", [
//                        'original' => "{$originalWidth}x{$originalHeight}",
//                        'target' => "{$targetWidth}x{$targetHeight}",
//                    ]);
                } else {
                    // 둘 다 충분히 큼 → width, height에 맞춰 리사이징
                    $img->cover($targetWidth, $targetHeight);

//                    $this->log->info("이미지 리사이징 (가로/세로 맞춤)", [
//                        'original' => "{$originalWidth}x{$originalHeight}",
//                        'target' => "{$targetWidth}x{$targetHeight}",
//                    ]);
                }
            }

            // 고품질 유지 (기본 100)
            $quality = $options['quality'] ?? 100;

            // 임시 파일에 저장
            $extension = $file->getClientOriginalExtension();
            $tempPath = sys_get_temp_dir() . '/' . uniqid('resized_') . '.' . $extension;

            // 확장자에 따라 저장
            $lowerExt = strtolower($extension);
            if (in_array($lowerExt, ['jpg', 'jpeg'])) {
                $img->toJpeg($quality)->save($tempPath);
            } elseif ($lowerExt === 'png') {
                $img->toPng()->save($tempPath); // PNG는 무손실
            } elseif ($lowerExt === 'webp') {
                $img->toWebp($quality)->save($tempPath);
            } else {
                $img->save($tempPath);
            }

            $resizedInfo = [
                'original' => $file->getClientOriginalName(),
                'original_size' => "{$originalWidth}x{$originalHeight}",
                'resized_width' => $img->width(),
                'resized_height' => $img->height(),
                'quality' => $quality,
            ];

//            $this->log->info("이미지 리사이징 완료", $resizedInfo);

            // UploadedFile 객체로 변환
            return new UploadedFile(
                $tempPath,
                $file->getClientOriginalName(),
                $file->getMimeType(),
                0,
                true
            );

        } catch (\Exception $e) {
            $this->log->warning("이미지 리사이징 실패, 원본 사용: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 이미지 파일인지 확인
     *
     * @param UploadedFile $file
     * @return bool
     */
    protected function isImage(UploadedFile $file): bool
    {
        $imageMimeTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
        ];

        return in_array($file->getMimeType(), $imageMimeTypes);
    }

    /**
     * S3에서 기존 파일을 새 태그명으로 복사
     *
     * @param string $sourcePath 원본 S3 경로 (fpath)
     * @param string|null $newTag 새로운 태그 (null이면 태그 없이 복사)
     * @param string|null $originalFilename 원본 파일명 (fname, 확장자 추출용)
     * @param array $options 추가 옵션
     * @return array 새로 복사된 파일 정보 ['original_filename', 's3_path', 's3_url']
     * @throws \Exception
     */
    public function copyFileWithNewTag(string $sourcePath, ?string $newTag = null, ?string $originalFilename = null, array $options = []): array
    {
        try {
            $s3Disk = $options['s3_disk'] ?? 's3';

            // 원본 파일 존재 확인
            if (!Storage::disk($s3Disk)->exists($sourcePath)) {
                throw new \Exception("원본 파일이 존재하지 않습니다: {$sourcePath}");
            }

            // 확장자 추출 (원본 파일명이 제공되면 그것에서, 아니면 경로에서)
            if ($originalFilename) {
                $pathInfo = pathinfo($originalFilename);
            } else {
                $pathInfo = pathinfo($sourcePath);
            }
            $extension = $pathInfo['extension'] ?? '';

            // 새 파일명 생성
            $timestamp = now()->format('YmdHis');
            $random = Str::random(8);

            $newFilename = $newTag
                ? "{$timestamp}_{$random}_{$newTag}.{$extension}"
                : "{$timestamp}_{$random}.{$extension}";

            // 기존 파일의 디렉토리를 그대로 사용
            $directory = dirname($sourcePath);
            $newPath = $directory . '/' . $newFilename;

            // S3에서 파일 복사
            $sourceFullPath = ltrim($sourcePath, '/');
            $destinationFullPath = ltrim($newPath, '/');

            Storage::disk($s3Disk)->copy($sourceFullPath, $destinationFullPath);

            // 새 파일 URL 생성
            $s3Url = Storage::disk($s3Disk)->url('/' . $destinationFullPath);

            $this->log->info("S3 파일 복사 완료", [
                'source' => $sourcePath,
                'destination' => '/' . $destinationFullPath,
                'new_tag' => $newTag,
            ]);

            return [
                'original_filename' => $originalFilename,
                's3_path' => '/' . $destinationFullPath,
                's3_url' => $s3Url,
            ];

        } catch (\Exception $e) {
            $this->log->error("S3 파일 복사 실패: " . $e->getMessage(), [
                'source_path' => $sourcePath,
                'new_tag' => $newTag,
            ]);

            throw new \Exception("파일 복사 실패: " . $e->getMessage(), 0, $e);
        }
    }
}
