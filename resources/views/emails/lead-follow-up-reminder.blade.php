<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Daily Call List</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.5;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        
        .summary {
            background-color: #f5f5f5;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #007cba;
        }
        
        .priority-section {
            margin: 30px 0;
        }
        
        .priority-header {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f0f0f0;
        }
        
        .high-priority .priority-header {
            background-color: #ffe6e6;
            color: #cc0000;
        }
        
        .lead-item {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fafafa;
        }
        
        .lead-title {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .lead-value {
            color: #007cba;
            font-weight: bold;
            float: right;
        }
        
        .contact-info {
            margin: 10px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border-left: 3px solid #007cba;
        }
        
        .contact-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .days-waiting {
            font-weight: bold;
            color: #666;
        }
        
        .view-link {
            background-color: #007cba;
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 3px;
            display: inline-block;
            margin-top: 10px;
        }
        
        .footer {
            margin-top: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-top: 2px solid #ddd;
        }
        
        .no-leads {
            text-align: center;
            padding: 30px;
            background-color: #f0f8ff;
            border: 1px solid #ddd;
        }
        
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
    </style>
</head>
<body>
    <h1>Daily Call List - {{ date('F j, Y') }}</h1>

    <div class="summary">
        <p><strong>Hi {{ $salesOwner->name }}!</strong></p>
        <p>You have <strong>{{ $totalLeads }} leads</strong> ready for phone follow-up today.</p>
    </div>

    @if($highCount > 0)
        <div class="priority-section high-priority">
            <div class="priority-header">
                HIGH PRIORITY ({{ $highCount }}) - Call First
            </div>
            
            @foreach($highPriorityLeads as $lead)
                <div class="lead-item">
                    <div class="clearfix">
                        <div class="lead-title">{{ $lead->title }}</div>
                        <div class="lead-value">${{ number_format($lead->lead_value, 0) }}</div>
                    </div>
                    
                    <div class="contact-info">
                        @if($lead->person)
                            <div class="contact-name">{{ $lead->person->name }}</div>
                            @if($lead->person->contact_numbers && count($lead->person->contact_numbers) > 0)
                                <div>Phone: <a href="tel:{{ $lead->person->contact_numbers[0]['value'] ?? '' }}">{{ $lead->person->contact_numbers[0]['value'] ?? 'No phone' }}</a></div>
                            @endif
                            @if($lead->person->emails && count($lead->person->emails) > 0)
                                <div>Email: {{ $lead->person->emails[0]['value'] ?? 'No email' }}</div>
                            @endif
                        @else
                            <div>No contact info available</div>
                        @endif
                    </div>
                    
                    <div class="days-waiting">{{ $lead->businessDaysSinceUpdate }} days waiting</div>
                    <a href="{{ config('app.url') }}/admin/leads/view/{{ $lead->id }}" class="view-link">View Lead</a>
                </div>
            @endforeach
        </div>
    @endif

    @if($lowCount > 0)
        <div class="priority-section">
            <div class="priority-header">
                STANDARD PRIORITY ({{ $lowCount }})
            </div>
            
            @foreach($lowPriorityLeads as $lead)
                <div class="lead-item">
                    <div class="clearfix">
                        <div class="lead-title">{{ $lead->title }}</div>
                        <div class="lead-value">${{ number_format($lead->lead_value, 0) }}</div>
                    </div>
                    
                    <div class="contact-info">
                        @if($lead->person)
                            <div class="contact-name">{{ $lead->person->name }}</div>
                            @if($lead->person->contact_numbers && count($lead->person->contact_numbers) > 0)
                                <div>Phone: <a href="tel:{{ $lead->person->contact_numbers[0]['value'] ?? '' }}">{{ $lead->person->contact_numbers[0]['value'] ?? 'No phone' }}</a></div>
                            @endif
                            @if($lead->person->emails && count($lead->person->emails) > 0)
                                <div>Email: {{ $lead->person->emails[0]['value'] ?? 'No email' }}</div>
                            @endif
                        @else
                            <div>No contact info available</div>
                        @endif
                    </div>
                    
                    <div class="days-waiting">{{ $lead->businessDaysSinceUpdate }} days waiting</div>
                    <a href="{{ config('app.url') }}/admin/leads/view/{{ $lead->id }}" class="view-link">View Lead</a>
                </div>
            @endforeach
        </div>
    @endif

    @if($totalLeads == 0)
        <div class="no-leads">
            <h3>No leads need phone follow-up today!</h3>
        </div>
    @endif

    <div class="footer">
        <p><strong>Next Steps:</strong> Call each lead, update their status, and log your notes.</p>
        <p>Questions? Contact Grayson or Bailey.</p>
    </div>
</body>
</html> 