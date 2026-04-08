<?php

namespace App\Services\V1;

use App\Models\Admin\User;
use App\Services\BaseService;
use App\Services\Contracts\UserServiceInterface;
use App\Traits\HasPaginationResponse;
use Illuminate\Support\Facades\DB;

class UserService extends BaseService implements UserServiceInterface
{
    use HasPaginationResponse;
    private string $secretKey;

    public function __construct()
    {
        parent::__construct();
        $this->secretKey = env('SECRET_KEY');
    }

    /**
     * 회원 목록 조회 (페이징)
     *
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 항목 수
     * @param array $filters 검색 필터 (search, status, gubun 등)
     * @return array
     */
    public function getUserList(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $query = User::where('status', 1);

        // 검색 필터 적용 (복호화 후 검색)
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('account', 'ILIKE', "%{$search}%")
                  ->orWhereRaw("ota.aes_decrypt(phone_number, ?) ILIKE ?", [$this->secretKey, "%{$search}%"])
                  ->orWhereRaw("CONCAT(ota.aes_decrypt(first_name, ?), ' ', ota.aes_decrypt(last_name, ?)) ILIKE ?",
                      [$this->secretKey, $this->secretKey, "%{$search}%"]);
            });
        }

        // 상태 필터 (0:사용안함, 1:사용중)
        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }

        // 성별
        if (!empty($filters['gender'])) {
            $query->whereRaw("ota.aes_decrypt(sex, ?) = ?", [$this->secretKey, $filters['gender']]);
        }

        // 국적
        if (!empty($filters['nat_cd'])) {
            $query->where('sex', $filters['gender']);
        }

        if (isset($filters['keyword'])) {
            $query->where('account', 'like', "%{$filters['keyword']}%");
        }

        // 전체 개수 조회
        $total = $query->count();

        // 페이징 적용
        $items = $query->orderBy('scuser_seq', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->toArray();

        return $this->buildPaginationResponse($items, $total, $page, $perPage);
    }

    /**
     * 회원 상세 조회
     *
     * @param int $id 회원 번호
     * @return array
     * @throws \Exception
     */
    public function getUserDetail(int $id): array
    {
        $scuser = User::where('user_seq', $id)
            ->first();

        if (!$scuser) {
            throw new \Exception(json_encode([
                'code' => 'USER_NULL',
                'message' => ''
            ]));
        }

        return $scuser->toArray();
    }

    /**
     * 회원 수정
     *
     * @param int $id
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function updateUser(int $id, array $data): array
    {
        return $this->executeInTransaction(function () use ($id, $data) {

            $user = User::find($id);

            if (!$user) {
                throw new \Exception(json_encode([
                    'code' => 'USER_NULL',
                    'message' => ''
                ]));
            }

            $encryptFields = [
                'country_code', 'nationality_type', 'phone_number', 'sex'
            ];

            unset($data['__echo']);

            $sets = ['last_update_ts = ?'];
            $bindings = [date('Y-m-d H:i:s')];

            foreach ($data as $key => $value) {
                if (in_array($key, $encryptFields)) {
                    $sets[] = "{$key} = ota.aes_encrypt(?, ?)";
                    $bindings[] = $value;
                    $bindings[] = $this->secretKey;
                } else {
                    $sets[] = "{$key} = ?";
                    $bindings[] = $value;
                }
            }

            $bindings[] = $id;

//            $this->log->info('', $sets);
            // Eloquent update() 대신 직접 Raw Query 실행
            DB::update(
                "UPDATE user SET " . implode(', ', $sets) . " WHERE user_seq = ?",
                $bindings
            );

            return $this->getUserDetail($id);
        });
    }
}
