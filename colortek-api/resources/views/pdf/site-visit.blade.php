<!DOCTYPE html><html lang='ar' dir='rtl'><head><meta charset='utf-8'></head><body>
<h1>تقرير زيارة الموقع</h1>
@foreach($visit->answers as $answer) @if($answer->checklistItem)<p>{{ $answer->checklistItem->label_ar }}</p>@endif @endforeach
@foreach($visit->measurements as $row)<p>{{ $row->line_number }} {{ $row->element_name }}</p>@endforeach
</body></html>
