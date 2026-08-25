<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class SiteVisitDraftRequest extends FormRequest {
 public function authorize(): bool { return true; }
 public function rules(): array { return ['visited_on'=>['sometimes','date'],'answers'=>['sometimes','array']]; }
}
