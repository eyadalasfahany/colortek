<?php
declare(strict_types=1);
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
final class SiteVisitSubmitRequest extends FormRequest {
 public function authorize(): bool { return true; }
 public function rules(): array { return ['answers'=>['required','array'],'signed_attachment_id'=>['nullable','integer','exists:attachments,id']]; }
}
