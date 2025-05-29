{!! view_render_event('admin.leads.view.organization.before', ['lead' => $lead]) !!}

@if ($lead?->person?->organization)
    <div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
        <x-admin::accordion class="select-none !border-none">
            <x-slot:header class="!p-0">
                <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                    <h4>@lang('admin::app.leads.view.organizations.title')</h4>

                    @if (bouncer()->hasPermission('contacts.organizations.edit'))
                        <a
                            href="{{ route('admin.contacts.organizations.edit', $lead->person->organization->id) }}"
                            class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                            target="_blank"
                        ></a>
                    @endif
                </div>
            </x-slot>

            <x-slot:content class="mt-4 !px-0 !pb-0">
                <div class="flex gap-2">
                    {!! view_render_event('admin.leads.view.organization.avatar.before', ['lead' => $lead]) !!}

                    <!-- Organization Initials -->
                    <x-admin::avatar :name="$lead->person->organization->name" />

                    {!! view_render_event('admin.leads.view.organization.avatar.after', ['lead' => $lead]) !!}

                    <!-- Organization Details -->
                    <div class="flex flex-col gap-1">
                        {!! view_render_event('admin.leads.view.organization.name.before', ['lead' => $lead]) !!}

                        <span class="font-semibold text-brandColor">
                            {{ $lead->person->organization->name }}
                        </span>

                        {!! view_render_event('admin.leads.view.organization.name.after', ['lead' => $lead]) !!}

                        {!! view_render_event('admin.leads.view.organization.address.before', ['lead' => $lead]) !!}

                        @if ($lead->person->organization->address)
                            <div class="flex flex-col gap-0.5 dark:text-white">
                                @isset($lead->person->organization->address['address'])
                                    <span>
                                        {{ $lead->person->organization->address['address'] }}
                                    </span>
                                @endisset

                                @if(
                                    isset($lead->person->organization->address['postcode'])
                                    && isset($lead->person->organization->address['city'])
                                )
                                    <span>
                                        {{ $lead->person->organization->address['postcode'] . '  ' . $lead->person->organization->address['city'] }}
                                    </span>
                                @endif

                                @isset($lead->person->organization->address['state'])
                                    <span>
                                        {{ core()->state_name($lead->person->organization->address['state']) }}
                                    </span>
                                @endisset

                                @isset($lead->person->organization->address['country'])
                                    <span>
                                        {{ core()->country_name($lead->person->organization->address['country']) }}
                                    </span>
                                @endisset
                            </div>
                        @endif

                        {!! view_render_event('admin.leads.view.organization.address.after', ['lead' => $lead]) !!}
                    </div>
                </div>
            </x-slot>
        </x-admin::accordion>
    </div>
@endif

{!! view_render_event('admin.leads.view.organization.after', ['lead' => $lead]) !!} 