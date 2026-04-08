<?php

namespace App\Services\V1;

use App\Models\Admin\ScmenuGnb;
use App\Models\Admin\ScmenuSnb;
use App\Services\BaseService;
use App\Services\Contracts\MenuManagerServiceInterface;

class MenuManagerService extends BaseService implements MenuManagerServiceInterface
{
    public function __construct() {
        parent::__construct();
    }
    /**
     * GNB 전체 목록 조회
     *
     * @param string $lang 언어 코드 (KO, EN, JA, ZH, ZH-TW, ES)
     * @return array
     */
    public function getAllGnb(string $lang = 'KO'): array
    {
        $gnbs = ScmenuGnb::with(['snbs' => function ($query) {
                $query->where('user_gubun', 1) // 관리자용 메뉴만
                    ->where('is_active', true)
                    ->orderBy('order_no')
                    ->orderBy('ms_seq');
            }])
            ->where(function($query) {
                // SNB가 없거나, user_gubun=1인 SNB가 있는 GNB만
                $query->whereHas('snbs', function ($q) {
                    $q->where('user_gubun', 1)->where('is_active', true);
                })->orWhereDoesntHave('snbs');
            })
            ->where('is_active', true)
            ->orderBy('order_no')
            ->orderBy('mg_seq')
            ->get();

        // 언어에 맞는 메뉴명 반환
        return $gnbs->map(function ($gnb) use ($lang) {
            $data = $gnb->toArray();

            // 언어별 타이틀 설정
            if ($gnb->mg_title_language && isset($gnb->mg_title_language[$lang])) {
                $data['mg_title'] = $gnb->mg_title_language[$lang];
            }

            // SNB 언어별 타이틀 설정
            if (isset($data['snbs']) && is_array($data['snbs'])) {
                $data['snbs'] = array_map(function ($snb) use ($lang) {
                    if (isset($snb['sg_title_language'][$lang])) {
                        $snb['sg_title'] = $snb['sg_title_language'][$lang];
                    }
                    return $snb;
                }, $data['snbs']);
            }

            return $data;
        })->toArray();
    }

    /**
     * GNB 상세 조회
     *
     * @param int $mgSeq
     * @return array|null
     */
    public function getGnbDetail(int $mg_seq): ?array
    {
        $gnb = ScmenuGnb::where('mg_seq', $mg_seq)
            ->where('is_active', true)
            ->first();

        if (!$gnb) {
            throw new \Exception(json_encode([
                'code' => 'MENU_MANAGER_MENU_NULL',
                'message' => ''
            ]));
        }

        $data = $gnb->toArray();

        // JSONB 컬럼에서 다국어 데이터 추출
        $data['translations'] = $gnb->mg_title_language ?? [];

        return $data;
    }

    /**
     * GNB 생성
     *
     * @param array $data
     * @return array
     */
    public function createGnb(array $data): array
    {

        $exists = ScmenuGnb::where('mg_title', $data['title'])
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            throw new \Exception(json_encode([
                'code' => 'MENU_MANAGER_DUPLICATE_TITLE',
                'message' => ''
            ]));
        }

        return $this->executeInTransaction(function () use ($data) {
            // GNB 전체에서 가장 큰 order_no 조회
            $maxOrderNo = ScmenuGnb::where('is_active', true)->max('order_no') ?? 0;

            // 다국어 데이터 준비
            $translations = $data['translations'] ?? [];

            $gnb = ScmenuGnb::create([
                'mg_title' => $data['title'] ?? '',
                'mg_title_language' => $translations,  // JSONB 컬럼에 저장
                'order_no' => $maxOrderNo + 1,
                'view_yn' => $data['view_yn'] ?? 'Y',
                'is_active' => $data['is_active'] ?? true,
            ]);

            $codeMenuData = [
                'mg_seq' => $gnb->mg_seq,
                'depth' => 1,
                'scname' => $data['title'] ?? '',
                'translations' => $translations
            ];

            return $gnb->toArray();
        });
    }

    /**
     * GNB 수정
     */
    public function updateGnb(int $mg_seq, array $data): array
    {

        // 자기 자신을 제외하고 중복 검사
        $exists = ScmenuGnb::where('mg_title', $data['title'])
            ->where('mg_seq', '!=', $mg_seq)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            throw new \Exception(json_encode([
                'code' => 'MENU_MANAGER_DUPLICATE_TITLE',
                'message' => ''
            ]));
        }

        return $this->executeInTransaction(function () use ($mg_seq, $data) {

            $gnb = ScmenuGnb::where('mg_seq', $mg_seq)
                ->where('is_active', true)
                ->first();

            if(!$gnb) {
                throw new \Exception(json_encode([
                    'code' => 'MENU_MANAGER_MENU_NULL',
                    'message' => ''
                ]));
            }

            $updateData = [
                'mg_title' => $data['title'] ?? $gnb->mg_title,
                'view_yn' => $data['view_yn'] ?? $gnb->view_yn,
                'is_active' => $data['is_active'] ?? $gnb->is_active,
            ];

            // 다국어 데이터가 있으면 JSONB 컬럼 업데이트
            if (isset($data['translations']) && is_array($data['translations'])) {
                $updateData['mg_title_language'] = $data['translations'];
            }

            $gnb->update($updateData);

            $codeMenuData = [
                'mg_seq' => $gnb->mg_seq,
                'scname' => $data['title'] ?? '',
                'translations' => $gnb->mg_title_language,
            ];

            return $gnb->fresh()->toArray();
        });
    }

    /**
     * GNB 삭제 (soft delete)
     *
     * @param int $mgSeq
     * @return bool
     */
    public function deleteGnb(int $mg_seq): bool
    {

        $gnb = ScmenuGnb::where('mg_seq', $mg_seq)
            ->where('is_active', true)
            ->first();

        if(!$gnb) {
            throw new \Exception(json_encode([
                'code' => 'MENU_MANAGER_MENU_NULL',
                'message' => ''
            ]));
        }

        return $this->executeInTransaction(function () use ($mg_seq) {
            $gnb = ScmenuGnb::where('mg_seq', $mg_seq)
                ->firstOrFail();

            // GNB 삭제
            $gnb->update([
                'is_active' => false,
                'delete_ts' => now()
            ]);

            // 하위 SNB도 함께 삭제
            ScmenuSnb::where('mg_seq', $mg_seq)
                ->update([
                    'is_active' => false,
                    'delete_ts' => now()
                ]);

            return true;
        });
    }

    /**
     * GNB 순서 변경
     *
     * @param array $orders [['seq' => 1, 'order_no' => 1], ...]
     * @return bool
     */
    public function updateGnbOrder(array $orders): bool
    {
        return $this->executeInTransaction(function () use ($orders) {

            foreach ($orders as $order) {

                $gnb = ScmenuGnb::where('mg_seq', $order['seq'])
                    ->where('is_active', true)
                    ->first();

                if(!$gnb) {
                    throw new \Exception(json_encode([
                        'code' => 'MENU_MANAGER_MENU_NULL',
                        'message' => ''
                    ]));
                }

                ScmenuGnb::where('mg_seq', $order['seq'])
                    ->where('is_active', true)
                    ->update(['order_no' => $order['order_no']]);
            }
            return true;
        });
    }

    /**
     * 특정 GNB의 SNB 목록 조회
     *
     * @param int $mgSeq
     * @param string $lang 언어 코드 (KO, EN, JA, ZH, ZH-TW, ES)
     * @return array
     */
    public function getSnbByGnb(int $mg_seq, string $lang = 'KO'): array
    {

        $gnb = ScmenuGnb::where('mg_seq', $mg_seq)
            ->where('is_active', true)
            ->first();

        if(!$gnb) {
            throw new \Exception(json_encode([
                'code' => 'MENU_MANAGER_MENU_NULL',
                'message' => ''
            ]));
        }

        $snbs = ScmenuSnb::where('mg_seq', $mg_seq)
            ->where('user_gubun', 1) // 관리자용 메뉴만
            ->where('is_active', true)
            ->orderBy('order_no')
            ->orderBy('ms_seq')
            ->get();

        // 언어에 맞는 메뉴명 반환
        return $snbs->map(function ($snb) use ($lang) {
            $data = $snb->toArray();

            // 언어별 타이틀 설정
            if ($snb->sg_title_language && isset($snb->sg_title_language[$lang])) {
                $data['sg_title'] = $snb->sg_title_language[$lang];
            }

            return $data;
        })->toArray();
    }

    /**
     * SNB 상세 조회
     *
     * @param int $msSeq
     * @return array|null
     */
    public function getSnbDetail(int $ms_seq): ?array
    {
        $snb = ScmenuSnb::where('ms_seq', $ms_seq)
            ->where('is_active', true)
            ->first();

        if(!$snb) {
            throw new \Exception(json_encode([
                'code' => 'MENU_MANAGER_MENU_NULL',
                'message' => ''
            ]));
        }

        $data = $snb->toArray();

        // JSONB 컬럼에서 다국어 데이터 추출
        $data['translations'] = $snb->sg_title_language ?? [];

        return $data;
    }

    /**
     * SNB 생성
     *
     * @param array $data
     * @return array
     */
    public function createSnb(array $data): array
    {
        $mg_seq = $data['parent_seq'];

        $exists = ScmenuSnb::where('sg_title', $data['title'])
            ->where('mg_seq', $mg_seq)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            throw new \Exception(json_encode([
                'code' => 'MENU_MANAGER_DUPLICATE_TITLE',
                'message' => ''
            ]));
        }

        return $this->executeInTransaction(function () use ($data, $mg_seq) {

            // 해당 GNB 그룹 내에서 가장 큰 order_no 조회
            $maxOrderNo = ScmenuSnb::where('mg_seq', $mg_seq)
                ->where('is_active', true)
                ->max('order_no') ?? 0;

            // 다국어 데이터 준비
            $translations = $data['translations'] ?? [];

            $snb = ScmenuSnb::create([
                'mg_seq' => $mg_seq,
                'sg_title' => $data['title'] ?? '',
                'sg_title_language' => $translations,  // JSONB 컬럼에 저장
                'url' => $data['url'] ?? '',
                'order_no' => $maxOrderNo + 1,
                'view_yn' => $data['view_yn'] ?? 'Y',
                'is_active' => $data['is_active'] ?? true,
                'user_gubun' => 1, // 관리자용
            ]);

            return $snb->toArray();
        });
    }

    /**
     * SNB 수정
     *
     * @param int $msSeq
     * @param array $data
     * @return array
     */
    public function updateSnb(int $ms_seq, array $data): array
    {

        $mg_seq = $data['parent_seq'];

        // 자기 자신을 제외하고 중복 검사
        $exists = ScmenuSnb::where('sg_title', $data['title'])
            ->where('mg_seq', $mg_seq)
            ->where('ms_seq', '!=', $ms_seq)
            ->where('is_active', true)
            ->exists();

        if ($exists) {
            throw new \Exception(json_encode([
                'code' => 'MENU_MANAGER_DUPLICATE_TITLE',
                'message' => ''
            ]));
        }

        return $this->executeInTransaction(function () use ($ms_seq, $data) {

            $snb = ScmenuSnb::where('ms_seq', $ms_seq)
                ->where('is_active', true)
                ->first();

            if (!$snb) {
                throw new \Exception(json_encode([
                    'code' => 'MENU_MANAGER_MENU_NULL',
                    'message' => ''
                ]));
            }

            $updateData = [
                'sg_title' => $data['title'] ?? $snb->sg_title,
                'url' => $data['url'] ?? $snb->url,
                'order_no' => $data['order_no'] ?? $snb->order_no,
                'view_yn' => $data['view_yn'] ?? $snb->view_yn,
                'is_active' => $data['is_active'] ?? $snb->is_active,
            ];

            // 다국어 데이터가 있으면 JSONB 컬럼 업데이트
            if (isset($data['translations']) && is_array($data['translations'])) {
                $updateData['sg_title_language'] = $data['translations'];
            }

            $snb->update($updateData);

            return $snb->fresh()->toArray();
        });
    }

    /**
     * SNB 삭제 (soft delete)
     *
     * @param int $msSeq
     * @return bool
     */
    public function deleteSnb(int $msSeq): bool
    {
        $snb = ScmenuSnb::where('ms_seq', $msSeq)
            ->first();

        if (!$snb) {
            throw new \Exception(json_encode([
                'code' => 'MENU_MANAGER_MENU_NULL',
                'message' => ''
            ]));
        }

        $snb->update([
            'is_active' => false,
            'delete_ts' => now()
        ]);

        return true;
    }

    /**
     * SNB 순서 변경
     *
     * @param array $orders [['seq' => 1, 'order_no' => 1], ...]
     * @return bool
     */
    public function updateSnbOrder(array $orders): bool
    {
        return $this->executeInTransaction(function () use ($orders) {
            foreach ($orders as $order) {

                $snb = ScmenuSnb::where('ms_seq', $order['seq'])
                    ->where('user_gubun', 1) // 관리자용 메뉴만
                    ->where('is_active', true)
                    ->first();

                if(!$snb) {
                    throw new \Exception(json_encode([
                        'code' => 'MENU_MANAGER_MENU_NULL',
                        'message' => ''
                    ]));
                }

                ScmenuSnb::where('ms_seq', $order['seq'])
                    ->where('user_gubun', 1) // 관리자용 메뉴만
                    ->where('is_active', true)
                    ->update(['order_no' => $order['order_no']]);
            }
            return true;
        });
    }

}
