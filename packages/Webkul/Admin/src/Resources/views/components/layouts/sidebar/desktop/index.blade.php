<div
    class="fixed top-[60px] bottom-0 w-[250px] border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 overflow-y-auto"
>
    <nav class="w-full">
        <!-- Navigation Menu -->
        <div class="p-4 space-y-2">
            @foreach (menu()->getItems('admin') as $menuItem)
                <div class="group/item {{ $menuItem->isActive() ? 'active' : 'inactive' }}">
                    <a
                        class="flex gap-2 p-2.5 items-center cursor-pointer rounded-lg {{ $menuItem->isActive() == 'active' ? 'bg-brandColor text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 hover:dark:bg-gray-950' }}"
                        href="{{ ! in_array($menuItem->getKey(), ['settings', 'configuration']) && $menuItem->haveChildren() ? 'javascript:void(0)' : $menuItem->getUrl() }}"
                    >
                        <span class="{{ $menuItem->getIcon() }} text-2xl"></span>

                        <div class="flex-1 flex justify-between items-center font-medium">
                            <p>{{ core()->getConfigData('general.settings.menu.'.$menuItem->getKey()) ?? $menuItem->getName() }}</p>
                        </div>
                    </a>

                    <!-- Static Submenu Drawer -->
                    @if (
                        ! in_array($menuItem->getKey(), ['settings', 'configuration'])
                        && $menuItem->haveChildren()
                    )
                        <div class="mt-1 pl-4 border-l-2 border-gray-200 ml-3 {{ $menuItem->isActive() ? 'border-brandColor' : '' }}">
                            @foreach ($menuItem->getChildren() as $subMenuItem)
                                <a
                                    href="{{ $subMenuItem->getUrl() }}"
                                    class="block py-2 px-4 text-sm rounded-lg {{ $subMenuItem->isActive() ? 'text-brandColor bg-gray-100 dark:bg-gray-800 font-medium' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                                >
                                    {{ core()->getConfigData('general.settings.menu.'.$subMenuItem->getKey()) ?? $subMenuItem->getName() }}
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </nav>
</div>

@pushOnce('scripts')
<script type="text/x-template" id="sidebar-template">
    export default {
        data() {
            return {
                activeSubmenu: null
            }
        },

        methods: {
            toggleSubmenu(menuKey) {
                this.activeSubmenu = this.activeSubmenu === menuKey ? null : menuKey;
            }
        }
    }
</script>
@endPushOnce