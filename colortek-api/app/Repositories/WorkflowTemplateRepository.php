<?php declare(strict_types=1);
namespace App\Repositories; use App\Models\WorkflowTemplate; use Illuminate\Pagination\LengthAwarePaginator;
final class WorkflowTemplateRepository extends BaseRepository {
    public function __construct(){parent::__construct(WorkflowTemplate::class);} 
    public function paginateForAdmin(array $f=[],int $per=15): LengthAwarePaginator {
        $q=$this->query()->orderBy('code')->orderByDesc('version');
        if(!empty($f['code'])) $q->where('code',$f['code']);
        if(array_key_exists('is_active',$f)&&$f['is_active']!==null) $q->where('is_active',filter_var($f['is_active'],FILTER_VALIDATE_BOOL));
        return $this->paginate($q,$per);
    }
    public function findDraftByCode(string $code): ?WorkflowTemplate { return $this->query()->where('code',$code)->whereNull('published_at')->orderByDesc('version')->first(); }
    protected function notFoundMessage(): string { return __('Workflow template not found'); }
}