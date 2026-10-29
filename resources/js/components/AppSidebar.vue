<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Hash, LayoutGrid, Users } from '@lucide/vue';
import { computed, ref } from 'vue';
import PlayerController from '@/actions/App/Http/Controllers/Admin/PlayerController';
import RoundController from '@/actions/App/Http/Controllers/RoundController';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const dashboardPath = dashboard.url();
const pageUrl = computed(() => new URL(page.url, 'http://localhost'));
const onDashboard = computed(() => pageUrl.value.pathname === dashboardPath);
const currentRoundId = computed(() => {
    const match = pageUrl.value.pathname.match(/^\/rounds\/(\d+)$/);

    return match ? Number(match[1]) : null;
});

const rounds = computed(() => page.props.sidebarRounds);
const showAllRounds = ref(false);
const visibleRounds = computed(() =>
    showAllRounds.value ? rounds.value : rounds.value.slice(0, 5),
);
const hasMoreRounds = computed(() => rounds.value.length > 5);

const sidebarGlow =
    'radial-gradient(ellipse 90% 50% at 50% 0%, color-mix(in srgb, var(--primary) 8%, transparent) 0%, color-mix(in srgb, var(--primary) 3%, transparent) 45%, transparent 80%)';

const isAdmin = computed(() => page.props.auth.user?.is_admin ?? false);

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: 'Dashboard',
        href: dashboardPath,
        icon: LayoutGrid,
        isActive: onDashboard.value,
    },
    ...(isAdmin.value
        ? [
              {
                  title: 'Jogadores',
                  href: PlayerController.index.url(),
                  icon: Users,
              },
          ]
        : []),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <div class="relative isolate flex h-full min-h-0 flex-col">
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0 -z-10"
                :style="{ background: sidebarGlow }"
            />

            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" as-child class="h-14 p-1">
                            <Link :href="dashboard()">
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain :items="mainNavItems" />

                <SidebarGroup v-if="rounds.length" class="px-2 py-0">
                    <SidebarGroupLabel>Rodadas</SidebarGroupLabel>
                    <SidebarMenu>
                        <SidebarMenuItem
                            v-for="round in visibleRounds"
                            :key="round.id"
                        >
                            <SidebarMenuButton
                                as-child
                                :is-active="currentRoundId === round.id"
                                :tooltip="`Rodada #${round.id}${round.status === 'ativa' ? ' · ativa' : ''}`"
                            >
                                <Link
                                    :href="RoundController.show.url(round.id)"
                                >
                                    <Hash />
                                    <span>Rodada #{{ round.id }}</span>
                                    <span
                                        v-if="round.status === 'ativa'"
                                        class="inline-flex items-center group-data-[collapsible=icon]:hidden"
                                    >
                                        <span
                                            class="size-1.5 rounded-full bg-emerald-500"
                                        />
                                    </span>
                                    <span
                                        class="ml-auto shrink-0 text-[11px] font-normal text-muted-foreground group-data-[collapsible=icon]:hidden"
                                    >
                                        {{ round.starts_on }}
                                    </span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>

                        <SidebarMenuItem v-if="hasMoreRounds">
                            <SidebarMenuButton
                                type="button"
                                :tooltip="
                                    showAllRounds ? 'Ver menos' : 'Ver mais'
                                "
                                :aria-expanded="showAllRounds"
                                @click="showAllRounds = !showAllRounds"
                            >
                                <component
                                    :is="
                                        showAllRounds ? ChevronUp : ChevronDown
                                    "
                                />
                                <span>
                                    {{
                                        showAllRounds ? 'Ver menos' : 'Ver mais'
                                    }}
                                </span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </div>
    </Sidebar>
    <slot />
</template>
