<?php declare(strict_types=1);
namespace App\Repositories; use App\Models\Employee; use Illuminate\Pagination\LengthAwarePaginator;
final class EmployeeRepository extends BaseRepository {
    public function __construct(){parent::__construct(Employee::class);} 
    public function paginateForAdmin(array $f=[],int $per=15): LengthAwarePaginator {
        $q=$this->query()->with(['department','user'])->orderBy('name'); return $this->paginate($q,$per);
    }
    protected function notFoundMessage(): string { return __('Employee not found'); }
}