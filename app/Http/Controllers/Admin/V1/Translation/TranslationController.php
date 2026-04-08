<?php

namespace App\Http\Controllers\Admin\V1\Translation;

use App\Http\Controllers\Controller;
use App\Services\Contracts\TranslationServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TranslationController extends Controller
{
    public function __construct(
        private TranslationServiceInterface $translationService
    ) {
        parent::__construct();
    }

    /**
     * 번역 요청
     * POST /api/v1/translation
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function translate(Request $request): JsonResponse
    {
        // Request Body 검증
        $validated = $request->validate([
            'type' => 'required|string|in:ChatGPT,DeepL',
            'content' => 'required|string',
            'orgLanguage' => 'nullable|string|max:10',
            'requestLanguage' => 'nullable|array',
            'requestLanguage.*' => 'string|max:20',  // ZH-HANT 지원
            '__echo' => 'nullable|string'  // Laravel Echo 관련 파라미터 허용
        ]);

        return $this->handleServiceCall(function() use ($validated) {

            $type = $validated['type'];
            $content = $validated['content'];
            $orgLanguage = $validated['orgLanguage'] ?? '';
            $requestLanguage = $validated['requestLanguage'] ?? [];

            // 번역 대상 언어가 지정되지 않은 경우 기본 언어 사용
            if (empty($requestLanguage)) {
                $defaultLanguages = ['KO', 'EN', 'JA', 'ZH', 'ZH-TW', 'ES', 'FR', 'DE', 'PT', 'RU'];

                // 원본 언어가 있으면 제외
                if (!empty($orgLanguage)) {
                    $requestLanguage = array_filter($defaultLanguages, function($lang) use ($orgLanguage) {
                        return strtoupper($lang) !== strtoupper($orgLanguage);
                    });
                } else {
                    $requestLanguage = $defaultLanguages;
                }
            }

            // 번역 실행
            $translation = $this->translationService->translate(
                $type,
                $requestLanguage,
                $content,
                $orgLanguage
            );

            return [
                'translation' => $translation
            ];
        });
    }

    /**
     * ChatGPT를 사용한 번역
     * POST /api/v1/translation/chatgpt
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function translateWithChatGPT(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'orgLanguage' => 'nullable|string|max:10',
            'requestLanguage' => 'required|array',
            'requestLanguage.*' => 'string|max:10'
        ]);

        return $this->handleServiceCall(function() use ($validated) {
            $translation = $this->translationService->translateWithChatGPT(
                $validated['requestLanguage'],
                $validated['content'],
                $validated['orgLanguage'] ?? ''
            );

            return [
                'translation' => $translation
            ];
        });
    }

    /**
     * DeepL을 사용한 번역
     * POST /api/v1/translation/deepl
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function translateWithDeepL(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string',
            'orgLanguage' => 'nullable|string|max:10',
            'requestLanguage' => 'required|array',
            'requestLanguage.*' => 'string|max:10'
        ]);

        return $this->handleServiceCall(function() use ($validated) {
            $translation = $this->translationService->translateWithDeepL(
                $validated['requestLanguage'],
                $validated['content'],
                $validated['orgLanguage'] ?? ''
            );

            return [
                'translation' => $translation
            ];
        });
    }
}
