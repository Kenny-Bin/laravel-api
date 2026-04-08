<?php

namespace App\Services\Contracts;

use Illuminate\Http\UploadedFile;

interface FileUploadServiceInterface
{
    /**
     * 파일을 로컬 및 S3에 업로드하고 메타데이터 반환
     *
     * @param  UploadedFile  $file  업로드할 파일
     * @param  string|null  $directory  S3 및 로컬 저장 디렉토리 (선택사항)
     * @param  array  $options  추가 옵션 (예: visibility, disk 등)
     * @return array 업로드 결과 메타데이터
     *
     * @throws \Exception 업로드 실패 시
     */
    public function uploadFile(UploadedFile $file, ?string $directory = null, array $options = []): array;

    /**
     * 여러 파일을 일괄 업로드
     *
     * @param  array  $files  UploadedFile 배열
     * @param  string|null  $directory  저장 디렉토리
     * @param  array  $options  추가 옵션
     * @return array 업로드된 파일들의 메타데이터 배열
     */
    public function uploadMultipleFiles(array $files, ?string $directory = null, array $options = []): array;

    /**
     * 로컬 및 S3에서 파일 삭제
     *
     * @param  string  $localPath  로컬 파일 경로
     * @param  string  $s3Path  S3 파일 경로
     * @return bool 삭제 성공 여부
     */
    public function deleteFile(string $localPath, string $s3Path): bool;
}
