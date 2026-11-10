<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChevronDown,
    ChevronsUpDown,
    ChevronUp,
    CircleDollarSign,
    Clock,
    Coins,
    Flag,
    Gauge,
    Inbox,
    Trash2,
    TrendingUp,
    Trophy,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import RoundController from '@/actions/App/Http/Controllers/RoundController';
import RoundSubmissionController from '@/actions/App/Http/Controllers/RoundSubmissionController';
import ChipTrendChart from '@/components/ChipTrendChart.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardAction,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { getInitials } from '@/composables/useInitials';
import { formatShortDate, toDateKey } from '@/lib/dates';
import { dashboard } from '@/routes';

type Player = {
    id: number;
    name: string;
    avatar: string | null;
    pivot: { chips: number; eliminated_at: string | null };
};

type Snapshot = {
    user_id: number;
    recorded_on: string;
    chips: number;
};

const props = defineProps<{
    canManage: boolean;
    round: {
        id: number;
        starts_on: string | null;
        status: string;
        total_chips: number;
        players: Player[];
    };
    snapshots: Snapshot[];
    blinds: {
        day: number;
        small: number;
        big: number;
    };
}>();

defineOptions({
    layout: {
        transparentHeader: true,
        backdrop: true,
    },
});

const activePlayers = computed(() =>
    props.round.players.filter((player) => !player.pivot.eliminated_at),
);

const isActive = computed(() => props.round.status === 'ativa');

const statusLabel = computed(() =>
    props.round.status === 'ativa' ? 'Em andamento' : 'Encerrada',
);

const statusClass = computed(() =>
    props.round.status === 'ativa'
        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : 'border-border bg-muted text-muted-foreground',
);

const days = computed(() => [
    ...new Set(
        props.snapshots.map((snapshot) => toDateKey(snapshot.recorded_on)),
    ),
]);

const historyCellClass = (userId: number, day: string): string => {
    const snapshot = props.snapshots.find(
        (item) =>
            item.user_id === userId && toDateKey(item.recorded_on) === day,
    );

    return snapshot && snapshot.chips > 0 ? '' : 'text-muted-foreground';
};

const chipFormatter = new Intl.NumberFormat('pt-BR');
const formatChips = (chips: number): string => chipFormatter.format(chips);

const initialChipsPerPlayer = computed(() =>
    props.round.players.length > 0
        ? Math.floor(props.round.total_chips / props.round.players.length)
        : null,
);

const leader = computed(() => {
    if (activePlayers.value.length === 0) {
        return null;
    }

    const topChips = Math.max(
        ...activePlayers.value.map((player) => player.pivot.chips),
    );
    const leaders = activePlayers.value.filter(
        (player) => player.pivot.chips === topChips,
    );

    return {
        name: leaders.length > 1 ? 'Empate' : leaders[0].name,
        avatar: leaders.length > 1 ? null : leaders[0].avatar,
        chips: topChips,
        tied: leaders.length > 1,
        count: leaders.length,
    };
});

const lastSnapshotOn = computed(() => {
    const last = days.value[days.value.length - 1];

    return last ? formatShortDate(last) : null;
});

const roundCreatedOn = computed(() => {
    const value = props.round.starts_on;

    if (!value) {
        return null;
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return year && month && day ? `${day}/${month}/${year}` : value;
});

const closeOpen = ref(false);
const deleteOpen = ref(false);

const closeRound = () => {
    closeOpen.value = false;
    router.post(RoundController.close.url(props.round.id), {
        preserveScroll: true,
    });
};

const deleteRound = () => {
    deleteOpen.value = false;
    router.delete(RoundController.destroy.url(props.round.id));
};

// Sort history
const sortKey = ref<string | null>(null);
const sortDir = ref<'asc' | 'desc'>('asc');

function toggleSort(key: string) {
    if (sortKey.value !== key) {
        sortKey.value = key;
        sortDir.value = key === 'name' ? 'asc' : 'desc';
    } else if (sortDir.value === (key === 'name' ? 'asc' : 'desc')) {
        sortDir.value = key === 'name' ? 'desc' : 'asc';
    } else {
        sortKey.value = null;
    }
}

function sortIcon(key: string) {
    if (sortKey.value !== key) return ChevronsUpDown;
    return sortDir.value === 'asc' ? ChevronUp : ChevronDown;
}

const sortedPlayers = computed(() => {
    const players = [...props.round.players];
    const key = sortKey.value;

    if (!key) {
        return players;
    }

    const dir = sortDir.value === 'asc' ? 1 : -1;

    return players.sort((a, b) => {
        if (key === 'name') {
            return a.name.localeCompare(b.name, 'pt-BR') * dir;
        }

        const av = chipsAt(a.id, key);
        const bv = chipsAt(b.id, key);

        if (av === null && bv === null) {
            return a.name.localeCompare(b.name, 'pt-BR');
        }

        if (av === null) {
            return 1;
        }

        if (bv === null) {
            return -1;
        }

        return (av - bv) * dir || a.name.localeCompare(b.name, 'pt-BR');
    });
});

function chipsAt(userId: number, day: string): number | null {
    const snapshot = props.snapshots.find(
        (item) =>
            item.user_id === userId && toDateKey(item.recorded_on) === day,
    );

    return snapshot ? snapshot.chips : null;
}

const cell = (userId: number, day: string): string => {
    const chips = chipsAt(userId, day);

    if (chips === null) {
        return '—';
    }

    return chips === 0 ? 'Eliminado' : String(chips);
};
</script>

<template>
    <Head :title="`Rodada ${round.id}`" />

    <div
        class="relative isolate flex h-full flex-1 flex-col gap-5 overflow-x-auto p-4 md:p-6"
    >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <p
                    class="text-xs font-medium tracking-[0.18em] text-primary uppercase"
                >
                    Rodada
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <h1
                        class="text-2xl font-semibold tracking-tight md:text-3xl"
                    >
                        Rodada #{{ round.id }}
                    </h1>
                    <Badge variant="outline" :class="statusClass">
                        {{ statusLabel }}
                    </Badge>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="dashboard()">
                        <ArrowLeft class="size-4" />
                        Dashboard
                    </Link>
                </Button>

                <Button v-if="canManage" as-child variant="outline">
                    <Link :href="RoundSubmissionController.index.url(round.id)">
                        <Inbox class="size-4" />
                        Envios
                    </Link>
                </Button>

                <template v-if="canManage">
                    <Button
                        v-if="isActive"
                        type="button"
                        variant="outline"
                        @click="closeOpen = true"
                    >
                        <Flag class="size-4" />
                        Encerrar
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="deleteOpen = true"
                    >
                        <Trash2 class="size-4" />
                        Excluir
                    </Button>
                </template>
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
                class="relative grid gap-6 p-5 md:grid-cols-[1.35fr_1fr] md:p-6"
            >
                <div class="flex flex-col justify-between gap-5">
                    <div class="space-y-1">
                        <div
                            class="flex items-center gap-1.5 text-muted-foreground"
                        >
                            <Trophy class="size-3.5" />
                            <p
                                class="text-xs font-medium tracking-[0.16em] uppercase"
                            >
                                Líder da rodada
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <Avatar
                                v-if="leader && !leader.tied"
                                class="size-10 overflow-hidden rounded-full"
                            >
                                <AvatarImage
                                    v-if="leader.avatar"
                                    :src="leader.avatar"
                                    :alt="leader.name"
                                />
                                <AvatarFallback class="text-xs">
                                    {{ getInitials(leader.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <h2
                                class="text-3xl font-semibold tracking-tight md:text-4xl"
                            >
                                {{
                                    leader
                                        ? leader.tied
                                            ? 'Empate'
                                            : leader.name
                                        : 'Mesa vazia'
                                }}
                            </h2>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            <template v-if="leader">
                                {{ formatChips(leader.chips) }} fichas
                                <template v-if="leader.tied">
                                    · {{ leader.count }} jogadores empatados
                                </template>
                            </template>
                            <template
                                v-else-if="
                                    !activePlayers.length &&
                                    round.players.length
                                "
                            >
                                Todos os jogadores foram eliminados.
                            </template>
                            <template v-else>
                                Nenhum jogador ativo nesta rodada.
                            </template>
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <Clock class="size-3.5" />
                            {{
                                lastSnapshotOn
                                    ? `Último lançamento em ${lastSnapshotOn}`
                                    : 'Nenhum lançamento registrado ainda'
                            }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 self-center">
                    <div
                        class="rounded-xl border bg-background/70 p-3 backdrop-blur-sm"
                    >
                        <div
                            class="flex items-center gap-1.5 text-muted-foreground"
                        >
                            <TrendingUp class="size-3.5" />
                            <p
                                class="text-[11px] font-medium tracking-wide uppercase"
                            >
                                Blinds · Dia {{ blinds.day }}
                            </p>
                        </div>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ formatChips(blinds.small) }} /
                            {{ formatChips(blinds.big) }}
                        </p>
                        <p
                            class="truncate text-[11px] text-muted-foreground tabular-nums"
                        >
                            Small / Big
                        </p>
                    </div>

                    <div
                        class="rounded-xl border bg-background/70 p-3 backdrop-blur-sm"
                    >
                        <div
                            class="flex items-center gap-1.5 text-muted-foreground"
                        >
                            <Coins class="size-3.5" />
                            <p
                                class="text-[11px] font-medium tracking-wide uppercase"
                            >
                                Fichas em jogo
                            </p>
                        </div>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ formatChips(round.total_chips) }}
                        </p>
                        <p
                            class="truncate text-[11px] text-muted-foreground tabular-nums"
                        >
                            {{
                                initialChipsPerPlayer === null
                                    ? '—'
                                    : `${formatChips(initialChipsPerPlayer)} iniciais por jogador`
                            }}
                        </p>
                    </div>

                    <div
                        class="rounded-xl border bg-background/70 p-3 backdrop-blur-sm"
                    >
                        <div
                            class="flex items-center gap-1.5 text-muted-foreground"
                        >
                            <CalendarDays class="size-3.5" />
                            <p
                                class="text-[11px] font-medium tracking-wide uppercase"
                            >
                                Início da rodada
                            </p>
                        </div>
                        <p class="mt-1 text-lg font-semibold tabular-nums">
                            {{ roundCreatedOn ?? '—' }}
                        </p>
                        <p class="truncate text-[11px] text-muted-foreground">
                            Data de criação
                        </p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card class="min-w-0">
                <CardHeader>
                    <CardTitle>Histórico</CardTitle>
                    <CardDescription>
                        {{ days.length }}
                        {{
                            days.length === 1
                                ? 'dia registrado'
                                : 'dias registrados'
                        }}
                    </CardDescription>
                </CardHeader>

                <CardContent class="flex-1">
                    <div
                        v-if="days.length"
                        class="overflow-x-auto rounded-lg border"
                    >
                        <table class="w-full table-fixed text-sm">
                            <thead
                                class="bg-muted/50 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="px-3 py-2.5 font-medium">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 uppercase hover:text-foreground"
                                            @click="toggleSort('name')"
                                        >
                                            Jogador
                                            <component
                                                :is="sortIcon('name')"
                                                class="size-3"
                                            />
                                        </button>
                                    </th>
                                    <th
                                        v-for="day in days"
                                        :key="day"
                                        class="px-3 py-2.5 text-center font-medium whitespace-nowrap"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 uppercase hover:text-foreground"
                                            @click="toggleSort(day)"
                                        >
                                            {{ formatShortDate(day) }}
                                            <component
                                                :is="sortIcon(day)"
                                                class="size-3"
                                            />
                                        </button>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="player in sortedPlayers"
                                    :key="player.id"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2.5">
                                        <span
                                            class="inline-flex items-center gap-2"
                                        >
                                            <Avatar
                                                class="size-6 overflow-hidden rounded-full"
                                            >
                                                <AvatarImage
                                                    v-if="player.avatar"
                                                    :src="player.avatar"
                                                    :alt="player.name"
                                                />
                                                <AvatarFallback
                                                    class="text-[10px]"
                                                >
                                                    {{
                                                        getInitials(player.name)
                                                    }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <span
                                                :class="
                                                    player.pivot.eliminated_at
                                                        ? 'text-muted-foreground line-through'
                                                        : ''
                                                "
                                            >
                                                {{ player.name }}
                                            </span>
                                        </span>
                                    </td>
                                    <td
                                        v-for="day in days"
                                        :key="day"
                                        class="px-3 py-2.5 text-center tabular-nums"
                                        :class="
                                            historyCellClass(player.id, day)
                                        "
                                    >
                                        {{ cell(player.id, day) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhum dia registrado.
                    </p>
                </CardContent>
            </Card>

            <Card class="min-w-0">
                <CardHeader>
                    <CardTitle>Evolução</CardTitle>
                    <CardDescription>
                        Fichas por jogador ao longo dos dias registrados.
                    </CardDescription>
                </CardHeader>

                <CardContent class="flex-1">
                    <ChipTrendChart
                        v-if="days.length"
                        :players="round.players"
                        :snapshots="snapshots"
                    />

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhum dia registrado.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="closeOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Encerrar a rodada #{{ round.id }}?
                    </DialogTitle>
                    <DialogDescription>
                        Depois de encerrada, não será possível registrar novas
                        fichas. O histórico continua disponível para consulta.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Cancelar</Button>
                    </DialogClose>
                    <Button @click="closeRound">Encerrar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="deleteOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Excluir a rodada #{{ round.id }}?
                    </DialogTitle>
                    <DialogDescription>
                        O histórico, os jogadores e os snapshots dessa rodada
                        serão apagados. Essa ação não pode ser desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="deleteRound">
                        Excluir
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
