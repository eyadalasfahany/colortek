<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sample Approval Form</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; margin-bottom: 24px; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 4px 0; }
        .reference { font-size: 28px; font-weight: bold; text-align: center; margin: 20px 0; }
        .presale { background: #fff3cd; padding: 8px; margin-bottom: 12px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        td { padding: 6px 8px; vertical-align: top; }
        .label { width: 30%; font-weight: bold; }
        .boxes { display: table; width: 100%; margin-top: 24px; }
        .box { display: table-cell; width: 50%; border: 1px solid #333; padding: 16px; text-align: center; }
        .signature { margin-top: 40px; }
        .signature-line { border-top: 1px solid #333; width: 45%; display: inline-block; margin-top: 48px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Colortek</h1>
        <p>نموذج موافقة العينة / Sample Approval Form</p>
        <p>{{ $generatedAt->format('Y-m-d') }}</p>
    </div>

    @if($sample->is_presale)
        <div class="presale">Pre-sale sample / عينة ما قبل البيع — no paid project yet</div>
    @endif

    <table>
        <tr>
            <td class="label">Client / العميل</td>
            <td>{{ $sample->client->name }}</td>
            <td class="label">Project / المشروع</td>
            <td>{{ $sample->project?->name ?? 'Pre-sale' }}</td>
        </tr>
        <tr>
            <td class="label">Quotation / عرض السعر</td>
            <td>{{ $sample->project?->quotation?->number ?? '—' }}</td>
            <td class="label">Attempt / المحاولة</td>
            <td>{{ $sample->attempt_number }}</td>
        </tr>
    </table>

    <div class="reference">{{ $sample->reference }}</div>

    <table>
        <tr><td class="label">Colour / اللون</td><td>{{ $sample->color }}</td></tr>
        <tr><td class="label">Texture / الملمس</td><td>{{ $sample->texture ?? '—' }}</td></tr>
        <tr><td class="label">Client reference</td><td>{{ $sample->client_reference ?? '—' }}</td></tr>
        <tr><td class="label">Size / الحجم</td><td>{{ $sample->size ?? '—' }}</td></tr>
        <tr><td class="label">Finish requirement</td><td>{{ $sample->finish_requirement ?? '—' }}</td></tr>
    </table>

    @if($sample->attempt_number > 1)
        <p><strong>Previous attempt rejection:</strong> {{ $previousRejection ?? '—' }}</p>
    @endif

    <div class="boxes">
        <div class="box">موافق / Approved</div>
        <div class="box">غير موافق / Not approved</div>
    </div>

    <p><strong>Comments / ملاحظات:</strong></p>
    <p style="border:1px solid #ccc; min-height:60px;"></p>

    <div class="signature">
        <span class="signature-line">توقيع العميل / Client signature</span>
        <span style="display:inline-block; width:8%;"></span>
        <span class="signature-line">توقيع مندوب المبيعات / Sales signature</span>
    </div>
</body>
</html>
