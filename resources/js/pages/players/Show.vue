<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronRight,
    Coins,
    Flame,
    Percent,
    Trophy,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import RoundController from '@/actions/App/Http/Controllers/RoundController';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { getInitials } from '@/composables/useInitials';
import { dashboard } from '@/routes';

type PlayerProfile = {
    id: number;
    name: string;
    avatar: string | null;
    is_admin?: boolean;
};

type PlayerStats = {
    wins: number;
    rounds_played: number;
    win_rate: number | null;
    streak: number;
};

type PlayerRound = {
    id: number;
    starts_on: string | null;
    status: string;
    players_count: number;
    chips: number;
    eliminated_at: string | null;
    won: boolean;
};

const {
    player,
    stats = null,
    rounds = [],
} = defineProps<{
    player: PlayerProfile;
    stats?: PlayerStats | null;
    rounds?: PlayerRound[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard.url(),
            },
            {
                title: 'Jogador',
                href: dashboard.url(),
            },
        ],
    },
});

const chipFormatter = new Intl.NumberFormat('pt-BR');
const percentFormatter = new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 1,
});

const formatChips = (chips: number): string => chipFormatter.format(chips);
const formatPercent = (value: number): string =>
    `${percentFormatter.format(value)}%`;

const statCards = computed(() => [
    {
        label: 'Vitórias',
        value: stats ? String(stats.wins) : '—',
        icon: Trophy,
    },
    {
        label: 'Rodadas jogadas',
        value: stats ? String(stats.rounds_played) : '—',
        icon: Users,
    },
    {
        label: 'Aproveitamento',
        value:
            stats && stats.win_rate !== null
                ? formatPercent(stats.win_rate)
                : '—',
        icon: Percent,
    },
    {
        label: 'Sequência',
        value: stats ? String(stats.streak) : '—',
        icon: Flame,
    },
]);

const orderedRounds = computed(() => [...rounds].sort((a, b) => b.id - a.id));

const roundStatusLabel = (status: string): string =>
    status === 'ativa' ? 'Em andamento' : 'Encerrada';

const roundStatusClass = (status: string): string =>
    status === 'ativa'
        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : 'border-border bg-muted text-muted-foreground';

const roundResult = (round: PlayerRound): { label: string; class: string } => {
    if (round.status === 'ativa') {
        return {
            label: 'Em jogo',
            class: 'border-primary/20 bg-primary/10 text-primary',
        };
    }

    if (round.won) {
        return {
            label: 'Vencedor',
            class: 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
        };
    }

    if (round.eliminated_at) {
        return {
            label: 'Eliminado',
            class: 'border-border bg-muted text-muted-foreground',
        };
    }

    return {
        label: 'Participou',
        class: 'border-border bg-muted text-muted-foreground',
    };
};

const visitRound = (roundId: number) => {
    router.visit(RoundController.show.url(roundId));
};
</script>

<template>
    <Head :title="player.name" />

    <div
        class="relative isolate flex h-full flex-1 flex-col gap-5 overflow-x-auto p-4 md:p-6"
    >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <Button as-child variant="ghost" size="sm" class="-ml-2">
                    <Link :href="dashboard()">
                        <ArrowLeft class="size-4" />
                        Voltar
                    </Link>
                </Button>
                <p
                    class="text-xs font-medium tracking-[0.18em] text-primary uppercase"
                >
                    Jogador
                </p>
                <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">
                    {{ player.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Perfil individual e histórico de rodadas
                </p>
            </div>
        </header>

        <Card
            class="relative overflow-hidden border-primary/15 bg-linear-to-br from-primary/10 via-card to-card py-0"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-24 size-64 rounded-full bg-primary/10 blur-3xl"
                aria-hidden="true"
            />
            <CardContent
                class="relative flex flex-wrap items-center gap-4 p-5 md:p-6"
            >
                <Avatar
                    class="size-16 overflow-hidden rounded-full ring-2 ring-primary/10"
                >
                    <AvatarImage
                        v-if="player.avatar"
                        :src="player.avatar"
                        :alt="player.name"
                    />
                    <AvatarFallback class="text-lg font-semibold">
                        {{ getInitials(player.name) }}
                    </AvatarFallback>
                </Avatar>

                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-xl font-semibold">
                            {{ player.name }}
                        </h2>
                        <Badge
                            v-if="player.is_admin"
                            variant="outline"
                            class="border-primary/20 bg-primary/10 text-primary"
                        >
                            Administrador
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            stats
                                ? `${stats.rounds_played} rodadas · ${stats.wins} vitórias`
                                : 'Sem estatísticas registradas ainda'
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Card v-for="card in statCards" :key="card.label" class="py-0">
                <CardContent class="flex items-center gap-3 p-4">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <component :is="card.icon" class="size-5" />
                    </span>
                    <div class="min-w-0">
                        <p
                            class="truncate text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ card.label }}
                        </p>
                        <p class="text-lg font-semibold tabular-nums">
                            {{ card.value }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Histórico de rodadas</CardTitle>
                <CardDescription>
                    Participações deste jogador nas rodadas registradas.
                </CardDescription>
            </CardHeader>

            <CardContent>
                <div
                    v-if="orderedRounds.length"
                    class="overflow-x-auto rounded-lg border"
                >
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-3 py-2.5 text-left font-medium">
                                    Rodada
                                </th>
                                <th class="px-3 py-2.5 text-center font-medium">
                                    Início
                                </th>
                                <th class="px-3 py-2.5 text-center font-medium">
                                    Jogadores
                                </th>
                                <th class="px-3 py-2.5 text-center font-medium">
                                    Fichas
                                </th>
                                <th class="px-3 py-2.5 text-center font-medium">
                                    Resultado Individual
                                </th>
                                <th class="px-3 py-2.5 text-center font-medium">
                                    Status da Rodada
                                </th>
                                <th class="px-3 py-2.5"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="round in orderedRounds"
                                :key="round.id"
                                class="cursor-pointer border-t transition-colors hover:bg-muted/30 focus-visible:bg-muted/50 focus-visible:outline-none"
                                tabindex="0"
                                role="link"
                                @click="visitRound(round.id)"
                                @keydown.enter="visitRound(round.id)"
                            >
                                <td class="px-3 py-2.5 text-left font-medium">
                                    #{{ round.id }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-center text-muted-foreground"
                                >
                                    {{ round.starts_on ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    {{ round.players_count }}
                                </td>
                                <td
                                    class="px-3 py-2.5 text-center tabular-nums"
                                >
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <Coins
                                            class="size-3.5 text-muted-foreground"
                                        />
                                        {{ formatChips(round.chips) }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <Badge
                                        variant="outline"
                                        :class="roundResult(round).class"
                                    >
                                        {{ roundResult(round).label }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <Badge
                                        variant="outline"
                                        :class="roundStatusClass(round.status)"
                                    >
                                        {{ roundStatusLabel(round.status) }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <ChevronRight
                                        class="ml-auto size-4 text-muted-foreground"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed py-10 text-center"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"
                    >
                        <CalendarDays class="size-5" />
                    </span>
                    <p class="text-sm font-medium">Nenhuma rodada registrada</p>
                    <p class="max-w-xs text-xs text-muted-foreground">
                        Este jogador ainda não participou de nenhuma rodada.
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
