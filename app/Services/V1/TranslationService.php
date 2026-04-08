<?php

namespace App\Services\V1;

use App\Services\BaseService;
use App\Services\Contracts\TranslationServiceInterface;
use GuzzleHttp\Client;
use OpenAI;

class TranslationService extends BaseService implements TranslationServiceInterface
{
    private string $openAiKey;

    private string $deepLKey;

    private string $deepLUrl;

    public function __construct()
    {
        parent::__construct();

        // 환경 변수에서 API 키 가져오기
        $this->openAiKey = config('services.openai.key', env('OPENAI_API_KEY', ''));
        $this->deepLKey = config('services.deepl.key', env('DEEPL_API_KEY', ''));
        $this->deepLUrl = config('services.deepl.url', env('DEEPL_API_URL', 'https://api-free.deepl.com/v2/translate'));
    }

    /**
     * 번역 실행 (API 타입에 따라 자동 선택)
     */
    public function translate(string $type, array $languageCodes, string $text, string $orgLanguage = ''): array
    {
        if ($type === 'ChatGPT') {
            return $this->translateWithChatGPT($languageCodes, $text, $orgLanguage);
        } elseif ($type === 'DeepL') {
            return $this->translateWithDeepL($languageCodes, $text, $orgLanguage);
        } else {
            throw new \Exception(json_encode([
                'code' => 'INVALID_TRANSLATION_TYPE',
                'message' => '',
            ]));
        }
    }

    /**
     * ChatGPT를 사용한 번역
     */
    public function translateWithChatGPT(array $languageCodes, string $text, string $orgLanguage = ''): array
    {
        if (empty($this->openAiKey)) {
            throw new \Exception(json_encode([
                'code' => 'OPENAI_KEY_NOT_SET',
                'message' => '',
            ]));
        }

        $client = OpenAI::client($this->openAiKey);

        // 줄바꿈을 특수 마커로 치환 (보존용)
        $lineBreakMarker = '___LINEBREAK___';
        $textWithMarkers = str_replace("\n", $lineBreakMarker, $text);

        // 원본 언어 지시문
        if ($orgLanguage != '') {
            $orgInstruction = "Use the provided original language code '{$orgLanguage}' and label it as ORG_LANG_CODE at the beginning of the output.";
        } else {
            $orgInstruction = 'Detect the original language of the following text and label its language code as ORG_LANG_CODE at the beginning of the output.';
        }

        // 번역 프롬프트 생성
        $prompt = $orgInstruction.' '
            .'Translate the following text into the following languages: '.implode(', ', $languageCodes).'. '
            .'Label each translation with its appropriate language code (e.g., KO, JA, ZH, ZH-TW, ES), followed by a colon. '
            .'Use the language code ZH for Simplified Chinese and ZH-TW for Traditional Chinese. '
            ."Use this format: 'ORG_LANG_CODE: [code] | KO: [Translation] | JA: [Translation] | ZH: [Simplified Chinese Translation] | ZH-TW: [Traditional Chinese Translation] | ...'. "
            ."Separate each translation with '|'. "
            ."IMPORTANT: The text contains '{$lineBreakMarker}' as a placeholder for line breaks. You must preserve these markers EXACTLY as they appear in the original text without translating or removing them. "
            .'Ensure that proper nouns such as personal names, organization names, place names, building names, and postal addresses are preserved as-is and not translated. '
            ."Translate naturally and fluently according to each language's conventions. "
            ."Here is the text: '".addslashes($textWithMarkers)."'";

        $response = $client->chat()->create([
            'model' => 'gpt-4o-2024-08-06',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (! isset($response['choices'][0]['message']['content'])) {
            throw new \Exception(json_encode([
                'code' => 'CHATGPT_INVALID_RESPONSE',
                'message' => '',
            ]));
        }

        $resultText = $response['choices'][0]['message']['content'];
        $resultArr = explode('|', $resultText);
        $result = [];

        foreach ($resultArr as $row) {
            $parts = explode(':', $row, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                // 줄바꿈 마커를 실제 줄바꿈으로 복원
                $value = str_replace($lineBreakMarker, "\n", $value);
                $result[$key] = $value;
            }
        }

        // 로그 저장
        // $this->saveTranslationLog(
        //     'ChatGPT',
        //     $text,
        //     $resultText,
        //     $response['usage']['prompt_tokens'] ?? 0,
        //     $response['usage']['completion_tokens'] ?? 0,
        //     $response['usage']['total_tokens'] ?? 0
        // );

        return $result;
    }

    /**
     * DeepL 언어 코드 매핑
     *
     * @param  string  $langCode  일반 언어 코드
     * @return string|null DeepL API 언어 코드 (지원하지 않는 언어는 null)
     */
    private function mapToDeepLLanguageCode(string $langCode): ?string
    {
        // DeepL이 지원하는 언어 코드 매핑
        $mapping = [
            'EN' => 'EN-US',
            'ZH' => 'ZH',           // 간체 중국어
            'ZH-TW' => 'ZH-HANT',   // 번체 중국어 (DeepL 지원)
            'ZH-HANT' => 'ZH-HANT', // 번체 중국어 (프론트에서 이미 변환해서 올 수 있음)
            'JA' => 'JA',
            'KO' => 'KO',
            'ES' => 'ES',
            'FR' => 'FR',
            'DE' => 'DE',
            'IT' => 'IT',
            'PT' => 'PT-PT',
            'RU' => 'RU',
            'NL' => 'NL',
            'PL' => 'PL',
            'TR' => 'TR',
            'SV' => 'SV',
            'DA' => 'DA',
            'FI' => 'FI',
            'NO' => 'NB',
            'CS' => 'CS',
            'BG' => 'BG',
            'EL' => 'EL',
            'HU' => 'HU',
            'RO' => 'RO',
            'SK' => 'SK',
            'SL' => 'SL',
            'UK' => 'UK',
            'ID' => 'ID',
            'LT' => 'LT',
            'LV' => 'LV',
            'ET' => 'ET',
        ];

        $upperCode = strtoupper($langCode);

        return $mapping[$upperCode] ?? $upperCode;
    }

    /**
     * DeepL을 사용한 번역
     */
    public function translateWithDeepL(array $languageCodes, string $text, string $orgLanguage = ''): array
    {
        if (empty($this->deepLKey)) {
            throw new \Exception(json_encode([
                'code' => 'DEEPL_KEY_NOT_SET',
                'message' => '',
            ]));
        }

        $client = new Client;
        $result = [];
        $logOutTextArr = [];

        // 원본 언어 설정
        $result['ORG_LANG_CODE'] = $orgLanguage ?: 'AUTO';

        // 줄바꿈을 특수 마커로 치환 (보존용)
        $lineBreakMarker = '___LINEBREAK___';
        $textWithMarkers = str_replace("\n", $lineBreakMarker, $text);

        $form = [
            'auth_key' => $this->deepLKey,
            'text' => $textWithMarkers,
            'preserve_formatting' => '1', // DeepL 포맷 보존 옵션
        ];

        if ($orgLanguage != '') {
            $form['source_lang'] = strtoupper($orgLanguage);
        }

        // 각 언어별로 번역 요청
        foreach ($languageCodes as $language) {
            try {
                // DeepL 언어 코드로 매핑
                $deeplLangCode = $this->mapToDeepLLanguageCode($language);

                // DeepL이 지원하지 않는 언어는 건너뛰기
                if ($deeplLangCode === null) {
                    $result[strtoupper($language)] = '[DeepL 미지원 언어]';

                    continue;
                }

                $form['target_lang'] = $deeplLangCode;

                $response = $client->request('POST', $this->deepLUrl, [
                    'form_params' => $form,
                ]);

                $body = json_decode($response->getBody()->getContents(), true);

                if (! isset($body['translations'][0]['text'])) {
                    throw new \Exception(json_encode([
                        'code' => 'DEEPL_INVALID_RESPONSE',
                        'message' => '',
                    ]));
                }

                $translated = $body['translations'][0]['text'] ?? '';

                // 줄바꿈 마커를 실제 줄바꿈으로 복원
                $translated = str_replace($lineBreakMarker, "\n", $translated);

                // 원본 언어 자동 감지 결과
                if (isset($body['translations'][0]['detected_source_language'])) {
                    $result['ORG_LANG_CODE'] = $body['translations'][0]['detected_source_language'];
                }

                $result[strtoupper($language)] = trim($translated);
                $logOutTextArr[] = strtoupper($language).': '.$result[strtoupper($language)];

            } catch (\Exception $e) {
                // 개별 언어 번역 실패 시 공백 반환
                $result[strtoupper($language)] = '';
            }
        }

        // 로그 저장
        // $this->saveTranslationLog(
        //     'DeepL',
        //     $text,
        //     implode(" | ", $logOutTextArr)
        // );

        // 최소 1개 언어라도 성공하면 결과 반환
        if (count($result) > 1) {
            return $result;
        }

        throw new \Exception(json_encode([
            'code' => 'TRANSLATION_ALL_FAILED',
            'message' => '',
        ]));
    }

    /**
     * 번역 로그 저장
     */
    // private function saveTranslationLog(
    //     string $apiType,
    //     string $inputText,
    //     string $outputText,
    //     int $inToken = 0,
    //     int $outToken = 0,
    //     int $totalToken = 0
    // ): void {
    //     try {
    //         // DB에 로그 저장
    //         \DB::connection('mysql_ota')->table('log_openai')->insert([
    //             'api_gubun' => $apiType === 'ChatGPT' ? 1 : 2,
    //             'gubun' => '번역 API',
    //             'in_txt' => $inputText,
    //             'out_txt' => $outputText,
    //             'in_token' => $inToken,
    //             'out_token' => $outToken,
    //             'total_token' => $totalToken,
    //             'adm_seq' => 0,
    //             'regdt' => now()
    //         ]);

    //     } catch (\Exception $e) {
    //         $this->log->error('[TranslationService] saveTranslationLog() 실패', [
    //             'error' => $e->getMessage()
    //         ]);
    //         // 로그 저장 실패는 번역 자체에 영향을 주지 않도록 예외를 던지지 않음
    //     }
    // }
}
