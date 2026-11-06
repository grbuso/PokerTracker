<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ChartLine,
    CircleDollarSign,
    Clock,
    Coins,
    Flag,
    Gauge,
    Table2,
    Trash2,
    TrendingUp,
    Trophy,
    UserX,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import RoundController from '@/actions/App/Http/Controllers/RoundController';
import ChipTrendChart from '@/components/ChipTrendChart.vue';
import InputError from '@/components/InputError.vue';
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

const isoToDisplay = (iso: string): string => {
    const [year, month, day] = iso.split('-');

    return year && month && day ? `${day}/${month}/${year}` : '';
};

const displayToIso = (display: string): string => {
    const match = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(display);

    if (!match) {
        return '';
    }

    const [, day, month, year] = match;
    const date = new Date(
        Date.UTC(Number(year), Number(month) - 1, Number(day)),
    );
    const isValid =
        date.getUTCFullYear() === Number(year) &&
        date.getUTCMonth() === Number(month) - 1 &&
        date.getUTCDate() === Number(day);

    return isValid ? `${year}-${month}-${day}` : '';
};

const maskDate = (value: string): string => {
    const digits = value.replace(/\D/g, '').slice(0, 8);

    if (digits.length <= 2) {
        return digits;
    }

    if (digits.length <= 4) {
        return `${digits.slice(0, 2)}/${digits.slice(2)}`;
    }

    return `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`;
};

const chipDenominations = [100, 50, 25, 10, 5] as const;

const emptyChipCounts = (): Record<number, number> =>
    chipDenominations.reduce<Record<number, number>>((counts, value) => {
        counts[value] = 0;

        return counts;
    }, {});

const chipCountsFromTotal = (total: number): Record<number, number> => {
    let remaining = Math.max(0, Math.floor(total));
    const counts = emptyChipCounts();

    for (const value of chipDenominations) {
        counts[value] = Math.floor(remaining / value);
        remaining -= counts[value] * value;
    }

    return counts;
};

const form = useForm({
    recorded_on: new Date().toLocaleDateString('sv-SE'),
    chips: {} as Record<number, number>,
});

const chipCounts = ref<Record<number, Record<number, number>>>({});

const recordedOnDisplay = ref(isoToDisplay(form.recorded_on));

const dateInput = ref<HTMLInputElement | null>(null);

const openDatePicker = () => {
    const input = dateInput.value;

    if (!input) {
        return;
    }

    if (typeof input.showPicker === 'function') {
        input.showPicker();
    } else {
        input.focus();
    }
};

const onDateInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const formatted = maskDate(target.value);

    recordedOnDisplay.value = formatted;
    target.value = formatted;
    form.recorded_on = displayToIso(formatted);
};

const onDatePicked = (event: Event) => {
    const target = event.target as HTMLInputElement;

    form.recorded_on = target.value;
    recordedOnDisplay.value = isoToDisplay(target.value);
};

const chipCountsTotal = (counts?: Record<number, number>): number =>
    chipDenominations.reduce(
        (total, value) => total + (counts?.[value] ?? 0) * value,
        0,
    );

const playerChipTotal = (userId: number): number =>
    chipCountsTotal(chipCounts.value[userId]);

const playerTotal = (userId: number): number =>
    Number(form.chips[userId] ?? 0) || 0;

const parseChipAmount = (value: string): number => {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? Math.max(0, Math.trunc(parsed)) : 0;
};

const onChipCountInput = (
    userId: number,
    denomination: number,
    event: Event,
): void => {
    const target = event.target as HTMLInputElement;

    chipCounts.value[userId][denomination] = parseChipAmount(target.value);
    form.chips[userId] = playerChipTotal(userId);
};

const onTotalInput = (userId: number, event: Event): void => {
    const target = event.target as HTMLInputElement;
    const total = parseChipAmount(target.value);

    form.chips[userId] = total;
    chipCounts.value[userId] = chipCountsFromTotal(total);
};

const eliminate = (player: Player) => {
    chipCounts.value[player.id] = emptyChipCounts();
    form.chips[player.id] = 0;
};

const activePlayersKey = computed(() =>
    activePlayers.value
        .map((player) => `${player.id}:${player.pivot.chips}`)
        .join('|'),
);

watch(
    activePlayersKey,
    () => {
        chipCounts.value = activePlayers.value.reduce<
            Record<number, Record<number, number>>
        >((counts, player) => {
            const existing = chipCounts.value[player.id];
            const pivotChips = Number(player.pivot.chips);

            counts[player.id] =
                existing && chipCountsTotal(existing) === pivotChips
                    ? existing
                    : chipCountsFromTotal(pivotChips);

            return counts;
        }, {});

        form.chips = activePlayers.value.reduce<Record<number, number>>(
            (chips, player) => {
                const existing = Number(form.chips[player.id]);
                const pivotChips = Number(player.pivot.chips);

                chips[player.id] =
                    existing === pivotChips ? existing : pivotChips;

                return chips;
            },
            {},
        );
    },
    { immediate: true },
);

const sum = computed(() =>
    activePlayers.value.reduce(
        (total, player) => total + playerTotal(player.id),
        0,
    ),
);

const sumMatches = computed(() => sum.value === props.round.total_chips);

const isActive = computed(() => props.round.status === 'ativa');

const dateIsValid = computed(
    () => displayToIso(recordedOnDisplay.value) !== '',
);

const canSave = computed(
    () =>
        isActive.value &&
        activePlayers.value.length > 0 &&
        sumMatches.value &&
        dateIsValid.value,
);

const statusLabel = computed(() =>
    props.round.status === 'ativa' ? 'Em andamento' : 'Encerrada',
);

const statusClass = computed(() =>
    props.round.status === 'ativa'
        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : 'border-border bg-muted text-muted-foreground',
);

const playerStatusClass = (eliminated: boolean): string =>
    eliminated
        ? 'border-destructive/20 bg-destructive/10 text-destructive'
        : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';

const days = computed(() => [
    ...new Set(props.snapshots.map((snapshot) => snapshot.recorded_on)),
]);

const cell = (userId: number, day: string): string => {
    const snapshot = props.snapshots.find(
        (item) => item.user_id === userId && item.recorded_on === day,
    );

    if (!snapshot) {
        return '—';
    }

    return snapshot.chips === 0 ? 'Eliminado' : String(snapshot.chips);
};

const formatDay = (day: string): string => {
    const [, month, date] = day.split('-');

    return month && date ? `${date}/${month}` : day;
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

    return last ? formatDay(last) : null;
});

const roundCreatedOn = computed(() => {
    const value = props.round.starts_on;

    if (!value) {
        return null;
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return year && month && day ? `${day}/${month}/${year}` : value;
});

const historyView = ref<'table' | 'chart'>('table');
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

const submit = () => {
    if (!isActive.value) {
        return;
    }

    activePlayers.value.forEach((player) => {
        form.chips[player.id] = playerTotal(player.id);
    });

    form.post(RoundController.storeSnapshot.url(props.round.id), {
        preserveScroll: true,
    });
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
            <Card>
                <CardHeader>
                    <CardTitle>
                        {{
                            isActive
                                ? canManage
                                    ? 'Registrar o dia'
                                    : 'Rodada em andamento'
                                : 'Rodada encerrada'
                        }}
                    </CardTitle>
                    <CardDescription
                        v-if="isActive && activePlayers.length && canManage"
                    >
                        Informe as denominações ou o total de fichas de quem
                        continua na mesa.
                    </CardDescription>
                    <CardDescription
                        v-else-if="isActive && activePlayers.length === 0"
                    >
                        Todos os jogadores desta rodada foram eliminados.
                    </CardDescription>
                    <CardAction
                        v-if="canManage && isActive && activePlayers.length"
                    >
                        <div class="grid justify-items-end gap-1">
                            <div
                                class="relative flex h-9 w-fit items-center overflow-hidden rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
                            >
                                <input
                                    id="recorded_on"
                                    v-model="recordedOnDisplay"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    maxlength="10"
                                    placeholder="DD/MM/AAAA"
                                    aria-label="Data do registro"
                                    class="h-full w-28 bg-transparent pr-2 pl-3 text-sm outline-none placeholder:text-muted-foreground"
                                    @input="onDateInput"
                                />
                                <input
                                    ref="dateInput"
                                    type="date"
                                    tabindex="-1"
                                    aria-hidden="true"
                                    :value="form.recorded_on"
                                    class="pointer-events-none absolute inset-0 h-full w-full [color-scheme:light] opacity-0 dark:[color-scheme:dark]"
                                    @change="onDatePicked"
                                />
                                <button
                                    type="button"
                                    class="flex h-full w-9 items-center justify-center border-l border-input text-muted-foreground transition-colors outline-none hover:bg-muted hover:text-foreground focus-visible:bg-muted focus-visible:text-foreground"
                                    aria-label="Abrir calendário"
                                    @click="openDatePicker"
                                >
                                    <CalendarDays class="size-4" />
                                </button>
                            </div>
                            <InputError :message="form.errors.recorded_on" />
                        </div>
                    </CardAction>
                </CardHeader>

                <CardContent>
                    <form
                        v-if="canManage && isActive && activePlayers.length"
                        class="space-y-4"
                        @submit.prevent="submit"
                    >
                        <div class="overflow-x-auto rounded-lg border">
                            <table class="w-full text-sm">
                                <thead
                                    class="bg-muted/50 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="px-3 py-2.5 font-medium">
                                            Jogador
                                        </th>
                                        <th
                                            v-for="value in chipDenominations"
                                            :key="value"
                                            class="px-1 py-2.5 text-center font-medium"
                                        >
                                            {{ value }}
                                        </th>
                                        <th
                                            class="px-2 py-2.5 text-center font-medium"
                                        >
                                            Total
                                        </th>
                                        <th class="px-2 py-2.5">
                                            <span class="sr-only">Ações</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="player in round.players"
                                        :key="player.id"
                                        class="border-t"
                                        :class="
                                            player.pivot.eliminated_at
                                                ? 'text-muted-foreground'
                                                : ''
                                        "
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
                                                            getInitials(
                                                                player.name,
                                                            )
                                                        }}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span
                                                    :class="
                                                        player.pivot
                                                            .eliminated_at
                                                            ? 'line-through'
                                                            : ''
                                                    "
                                                >
                                                    {{ player.name }}
                                                </span>
                                            </span>
                                        </td>
                                        <template
                                            v-if="!player.pivot.eliminated_at"
                                        >
                                            <td
                                                v-for="value in chipDenominations"
                                                :key="value"
                                                class="px-1 py-2.5 text-center"
                                            >
                                                <input
                                                    v-model.number="
                                                        chipCounts[player.id][
                                                            value
                                                        ]
                                                    "
                                                    type="number"
                                                    inputmode="numeric"
                                                    min="0"
                                                    :aria-label="`Fichas de ${value} de ${player.name}`"
                                                    class="h-8 w-12 rounded-md border border-input bg-transparent px-1 text-center text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                    @input="
                                                        onChipCountInput(
                                                            player.id,
                                                            value,
                                                            $event,
                                                        )
                                                    "
                                                />
                                            </td>
                                            <td class="px-2 py-2.5 text-center">
                                                <input
                                                    v-model.number="
                                                        form.chips[player.id]
                                                    "
                                                    type="number"
                                                    inputmode="numeric"
                                                    min="0"
                                                    :aria-label="`Total de fichas de ${player.name}`"
                                                    class="h-8 w-20 rounded-md border border-input bg-transparent px-1 text-center text-sm font-medium shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                    @input="
                                                        onTotalInput(
                                                            player.id,
                                                            $event,
                                                        )
                                                    "
                                                />
                                                <p
                                                    v-if="
                                                        playerChipTotal(
                                                            player.id,
                                                        ) !==
                                                        playerTotal(player.id)
                                                    "
                                                    class="mt-1 text-[10px] text-muted-foreground tabular-nums"
                                                >
                                                    Contagem:
                                                    {{
                                                        formatChips(
                                                            playerChipTotal(
                                                                player.id,
                                                            ),
                                                        )
                                                    }}
                                                </p>
                                            </td>
                                            <td class="px-2 py-2.5 text-center">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon-sm"
                                                    class="size-7 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                    :disabled="
                                                        playerTotal(
                                                            player.id,
                                                        ) === 0
                                                    "
                                                    :aria-label="`Eliminar ${player.name} e zerar as fichas`"
                                                    title="Eliminar (zerar fichas)"
                                                    @click="eliminate(player)"
                                                >
                                                    <UserX class="size-3.5" />
                                                </Button>
                                            </td>
                                        </template>
                                        <td
                                            v-else
                                            :colspan="
                                                chipDenominations.length + 2
                                            "
                                            class="px-2 py-2.5 text-center"
                                        >
                                            <Badge
                                                variant="outline"
                                                :class="playerStatusClass(true)"
                                            >
                                                Eliminado
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <InputError :message="form.errors.chips" />

                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <p
                                class="text-xs"
                                :class="
                                    sumMatches
                                        ? 'text-muted-foreground'
                                        : 'text-destructive'
                                "
                            >
                                Soma {{ formatChips(sum) }} /
                                {{ formatChips(round.total_chips) }}
                                <span v-if="!sumMatches">
                                    · precisa fechar antes de salvar
                                </span>
                            </p>
                            <Button
                                type="submit"
                                :disabled="form.processing || !canSave"
                            >
                                Salvar o dia
                            </Button>
                        </div>
                    </form>

                    <p
                        v-else-if="!isActive"
                        class="text-sm text-muted-foreground"
                    >
                        Não é possível alterar dados de rodadas encerradas.
                    </p>
                    <p
                        v-else-if="!canManage"
                        class="text-sm text-muted-foreground"
                    >
                        Apenas o administrador pode registrar as fichas do dia.
                    </p>
                    <p v-else class="text-sm text-muted-foreground">
                        Crie uma nova rodada no dashboard para continuar.
                    </p>
                </CardContent>
            </Card>

            <Card>
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
                    <CardAction>
                        <div
                            class="flex items-center gap-1 rounded-lg border p-0.5"
                        >
                            <Button
                                type="button"
                                :variant="
                                    historyView === 'table'
                                        ? 'secondary'
                                        : 'ghost'
                                "
                                size="sm"
                                :aria-pressed="historyView === 'table'"
                                @click="historyView = 'table'"
                            >
                                <Table2 class="size-3.5" />
                                <span class="hidden sm:inline">Tabela</span>
                            </Button>
                            <Button
                                type="button"
                                :variant="
                                    historyView === 'chart'
                                        ? 'secondary'
                                        : 'ghost'
                                "
                                size="sm"
                                :aria-pressed="historyView === 'chart'"
                                @click="historyView = 'chart'"
                            >
                                <ChartLine class="size-3.5" />
                                <span class="hidden sm:inline">Evolução</span>
                            </Button>
                        </div>
                    </CardAction>
                </CardHeader>

                <CardContent class="flex-1">
                    <template v-if="days.length">
                        <div
                            v-if="historyView === 'table'"
                            class="overflow-x-auto rounded-lg border"
                        >
                            <table class="w-full table-fixed text-sm">
                                <thead
                                    class="bg-muted/50 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                                >
                                    <tr>
                                        <th class="px-3 py-2.5 font-medium">
                                            Jogador
                                        </th>
                                        <th
                                            v-for="day in days"
                                            :key="day"
                                            class="px-3 py-2.5 text-center font-medium whitespace-nowrap"
                                        >
                                            {{ formatDay(day) }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="player in round.players"
                                        :key="player.id"
                                        class="border-t"
                                        :class="
                                            player.pivot.eliminated_at
                                                ? 'text-muted-foreground'
                                                : ''
                                        "
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
                                                            getInitials(
                                                                player.name,
                                                            )
                                                        }}
                                                    </AvatarFallback>
                                                </Avatar>
                                                <span
                                                    :class="
                                                        player.pivot
                                                            .eliminated_at
                                                            ? 'line-through'
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
                                        >
                                            {{ cell(player.id, day) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <ChipTrendChart
                            v-else
                            :players="round.players"
                            :snapshots="snapshots"
                        />
                    </template>

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
