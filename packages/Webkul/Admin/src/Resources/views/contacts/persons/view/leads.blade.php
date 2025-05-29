{!! view_render_event('admin.contacts.persons.view.leads.before', ['person' => $person]) !!}

@php
    $leads = $person->leads ?? collect();
@endphp

@if ($leads->count() > 0)
    <div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
        <x-admin::accordion class="select-none !border-none">
            <x-slot:header class="!p-0">
                <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                    <h4>@lang('admin::app.contacts.persons.view.leads.title') ({{ $leads->count() }})</h4>
                </div>
            </x-slot>

            <x-slot:content class="mt-4 !px-0 !pb-0">
                <div class="flex flex-col gap-3">
                    @foreach ($leads as $lead)
                        <div class="flex items-center justify-between rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                            <div class="flex flex-col gap-1">
                                {!! view_render_event('admin.contacts.persons.view.leads.title.before', ['person' => $person, 'lead' => $lead]) !!}

                                <a
                                    href="{{ route('admin.leads.view', $lead->id) }}"
                                    class="font-semibold text-brandColor hover:underline"
                                    target="_blank"
                                >
                                    {{ $lead->title }}
                                </a>

                                {!! view_render_event('admin.contacts.persons.view.leads.title.after', ['person' => $person, 'lead' => $lead]) !!}

                                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                    @if ($lead->lead_value)
                                        <span>{{ core()->formatBasePrice($lead->lead_value) }}</span>
                                        <span>•</span>
                                    @endif
                                    
                                    @if ($lead->stage)
                                        <span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700">
                                            {{ $lead->stage->name }}
                                        </span>
                                    @endif
                                </div>

                                @if ($lead->expected_close_date)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Expected close: {{ $lead->expected_close_date->format('M d, Y') }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if (bouncer()->hasPermission('leads.view'))
                                    <a
                                        href="{{ route('admin.leads.view', $lead->id) }}"
                                        class="icon-eye rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                                        target="_blank"
                                        title="View Lead"
                                    ></a>
                                @endif

                                @if (bouncer()->hasPermission('leads.edit'))
                                    <a
                                        href="{{ route('admin.leads.edit', $lead->id) }}"
                                        class="icon-edit rounded-md p-1.5 text-xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                                        target="_blank"
                                        title="Edit Lead"
                                    ></a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-slot>
        </x-admin::accordion>
    </div>
@endif

{!! view_render_event('admin.contacts.persons.view.leads.after', ['person' => $person]) !!} 