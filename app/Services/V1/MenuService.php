<?php

namespace App\Services\V1;

use App\Models\Admin\ScmenuGnb;
use App\Models\Admin\ScmenuSnb;
use App\Services\BaseService;
use App\Services\Contracts\MenuServiceInterface;

class MenuService extends BaseService implements MenuServiceInterface
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 메뉴 전체 조회 (GNB + SNB)
     *
     * @param string $lang 언어 코드 (KO, EN, JA, ZH, ZH-TW, ES)
     */
    public function getAllMenus(string $lang = 'KO'): array
    {
        try {
            $menus = ScmenuGnb::with(['snbs' => function ($query) {
                    $query->where('view_yn', 'Y')
                        ->where('is_active', true)
                        ->where('user_gubun', 1) // 관리자용 메뉴만
                        ->orderBy('order_no')
                        ->select(['ms_seq', 'mg_seq', 'sg_title', 'sg_title_language', 'order_no', 'view_yn', 'is_active', 'url']);
                }])
                ->where(function($query) {
                    // SNB가 없거나, user_gubun=1인 SNB가 있는 GNB만
                    $query->whereHas('snbs', function ($q) {
                        $q->where('user_gubun', 1)->where('is_active', true);
                    })->orWhereDoesntHave('snbs');
                })
                ->where('view_yn', 'Y')
                ->where('is_active', true)
                ->orderBy('order_no')
                ->select(['mg_seq', 'mg_title', 'mg_title_language', 'order_no', 'view_yn', 'is_active'])
                ->get();

            // 언어별 타이틀 추출
            $menus = $menus->map(function ($menu) use ($lang) {
                // GNB 타이틀을 언어별로 변경
                $menu->mg_title_localized = $menu->mg_title_language[$lang] ?? $menu->mg_title;

                // SNB도 언어별로 변경
                if ($menu->snbs) {
                    $menu->snbs->each(function ($snb) use ($lang) {
                        $snb->sg_title_localized = $snb->sg_title_language[$lang] ?? $snb->sg_title;
                    });
                }

                return $menu;
            });

            $result = [
                'menus' => $menus,
                'last_updated' => $this->getLastUpdatedTimestamp(),
            ];

            return $result;

        } catch (\Exception $e) {
            $this->log->error('[MenuService] getAllMenus() 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 최종 수정 시간 가져오기
     */
    public function getLastUpdatedTimestamp(): ?string
    {
        try {
//            $this->log->debug('[MenuService] getLastUpdatedTimestamp() 시작');

            $gnbLatest = ScmenuGnb::max('last_update_ts');
            $snbLatest = ScmenuSnb::where('user_gubun', 1)->max('last_update_ts'); // 관리자용 메뉴만

//            $this->log->debug('[MenuService] 타임스탬프 조회 완료', [
//                'gnb_latest' => $gnbLatest,
//                'snb_latest' => $snbLatest
//            ]);

            $latest = max($gnbLatest, $snbLatest);

            return $latest ? date('Y-m-d H:i:s', strtotime($latest)) : null;
        } catch (\Exception $e) {
            $this->log->error('[MenuService] getLastUpdatedTimestamp() 실패', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
