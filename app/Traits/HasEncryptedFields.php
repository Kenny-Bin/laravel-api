<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait HasEncryptedFields
{
    /**
     * 복호화 캐시 (동일 인스턴스에서 중복 복호화 방지)
     */
    protected array $decryptedCache = [];

    /**
     * 모델 retrieved 이벤트에서 자동 복호화 실행
     */
    protected static function bootHasEncryptedFields()
    {
        static::retrieved(function ($model) {
            $model->decryptFields();
        });
    }

    /**
     * 암호화된 필드들을 일괄 복호화
     */
    protected function decryptFields(): void
    {
        $secretKey = env('SECRET_KEY');
        $fieldsToDecrypt = [];

        // 복호화할 필드 수집
        foreach ($this->getEncryptedFields() as $field) {
            if (isset($this->attributes[$field]) && !empty($this->attributes[$field])) {
                $fieldsToDecrypt[] = $field;
            }
        }

        if (empty($fieldsToDecrypt)) {
            return;
        }

        // 한 번의 쿼리로 모든 필드 복호화
        $selectParts = [];
        $bindings = [];

        foreach ($fieldsToDecrypt as $field) {
            $selectParts[] = "aes_decrypt(?, ?) AS " . $field;
            $bindings[] = $this->attributes[$field];
            $bindings[] = $secretKey;
        }

        $sql = "SELECT " . implode(", ", $selectParts);

        try {
            $result = DB::selectOne($sql, $bindings);

            // 결과를 attributes에 저장
            foreach ($fieldsToDecrypt as $field) {
                $decrypted = $result->{$field} ?? null;
                $this->attributes[$field] = $decrypted;
                $this->decryptedCache[$field] = $decrypted;
            }
        } catch (\Exception $e) {
            // 복호화 실패 시 null로 설정
            foreach ($fieldsToDecrypt as $field) {
                $this->attributes[$field] = null;
                $this->decryptedCache[$field] = null;
            }
        }
    }

    /**
     * 단일 필드 복호화 (개별 필드 접근용 - 거의 사용되지 않음)
     *
     * @param string $field
     * @return string|null
     */
    protected function decryptField(string $field): ?string
    {
        // 캐시 확인
        if (isset($this->decryptedCache[$field])) {
            return $this->decryptedCache[$field];
        }

        $value = $this->attributes[$field];

        if (empty($value)) {
            return null;
        }

        $secretKey = env('SECRET_KEY');

        try {
            $result = DB::selectOne(
                "SELECT ota.aes_decrypt(?, ?) AS decrypted",
                [$value, $secretKey]
            );

            $decrypted = $result->decrypted ?? null;

            // 캐시에 저장
            $this->decryptedCache[$field] = $decrypted;

            return $decrypted;
        } catch (\Exception $e) {
            $this->decryptedCache[$field] = null;
            return null;
        }
    }

    /**
     * 복호화할 필드 목록 반환 (각 모델에서 구현)
     *
     * @return array
     */
    abstract protected function getEncryptedFields(): array;
}
