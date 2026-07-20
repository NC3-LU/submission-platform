<!-- resources/views/submissions/pdf-single.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Submission {{ $submission['id'] }} - {{ $form->title }}</title>
    <meta name="author" content="NC3 Luxembourg"/>
    <meta name="subject" content="Form Submission"/>
    <style>
        @page { size: A4; margin: 20mm; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #111827;
            background: #ffffff;
        }

        h1 { font-size: 16pt; margin: 0 0 8px; font-weight: 700; }
        h2 { font-size: 13pt; margin: 16px 0 8px; font-weight: 700; }
        .small { font-size: 9pt; color: #6b7280; }

        .header { margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .meta td { padding: 2px 0; font-size: 10pt; }

        .section { margin: 16px 0; page-break-inside: avoid; }
        .section-title { font-weight: 700; border-left: 3px solid #2563eb; padding-left: 8px; }
        .description { font-style: italic; color: #6b7280; margin: 6px 0 10px; }

        .field { margin: 10px 0; }
        .label { font-weight: 600; color: #374151; }
        .value { margin-top: 4px; }
        .value-block { white-space: pre-wrap; border: 1px solid #e5e7eb; padding: 8px; border-radius: 3px; background: #fafafa; }
        .muted { color: #9ca3af; font-style: italic; }

        ul { padding-left: 20px; margin: 8px 0; }
        li { margin-bottom: 4px; }

        .footer { margin-top: 24px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 9pt; color: #6b7280; }
    </style>
    </head>
<body>

<div class="header">
    <h1>{{ $form->title }}</h1>
    <table class="meta">
        <tr>
            <td>Submission #{{ $submission['id'] }}</td>
            <td style="text-align:right">Submitted: {{ $submission['created_at'] }}</td>
        </tr>
        <tr>
            <td colspan="2" class="small">Generated: {{ now()->format('Y-m-d H:i:s') }}</td>
        </tr>
    </table>
    </div>

@foreach($submission['categories'] as $category)
    <div class="section">
        <div class="section-title">{{ $category['name'] }}</div>
        @if($category['description'])
            <div class="description">{!! \App\Helpers\MarkdownHelper::toHtml($category['description']) !!}</div>
        @endif

        @foreach($category['fields'] as $field)
            <div class="field">
                <div class="label">{{ $field['label'] }}</div>
                <div class="value">
                    @if($field['type'] === 'file')
                        @if($field['displayValue'])
                            {{ basename($field['displayValue']) }}
                        @else
                            <span class="muted">—</span>
                        @endif
                    @elseif($field['type'] === 'textarea')
                        @if($field['displayValue'])
                            <div class="value-block">{{ $field['displayValue'] }}</div>
                        @else
                            <span class="muted">—</span>
                        @endif
                    @elseif($field['type'] === 'checkbox')
                        {{ $field['displayValue'] ? 'Yes' : 'No' }}
                    @elseif($field['type'] === 'radio' || $field['type'] === 'select')
                        {{ $field['displayValue'] ?: 'Not selected' }}
                    @else
                        {{ $field['displayValue'] ?: '' }}
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endforeach

<div class="footer">
    Luxembourg House of Cybersecurity • info@nc3.lu
    <br/>
    Generated: {{ now()->format('Y-m-d H:i:s') }}
    </div>

</body>
</html>
