<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Flag, Trophy, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import RoundController from '@/actions/App/Http/Controllers/RoundController';
import ChipTrendChart from '@/components/ChipTrendChart.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
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
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';

type Player = {
    id: number;
    name: string;
    chips: number;
    eliminated_at: string | null;
};

type Snapshot = {
    user_id: number;
    recorded_on: string;
    chips: number;
};

type ActiveRound = {
    id: number;
    starts_on: string | null;
    total_chips: number;
    players: Player[];
    snapshots: Snapshot[];
};

type RoundSummary = {
    id: number;
    starts_on: string | null;
    total_chips: number;
    status: string;
    players_count: number;
    winner: string | null;
    tie: boolean;
};

type RankingEntry = {
    id: number;
    name: string;
    wins: number;
    rounds_played: number;
    win_rate: number | null;
    streak: number;
};

const props = defineProps<{
    users: { id: number; name: string }[];
    activeRound: ActiveRound | null;
    rounds: RoundSummary[];
    ranking: RankingEntry[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
        transparentHeader: true,
        backdrop: true,
    },
});

const activePlayers = computed(
    () =>
        props.activeRound?.players.filter((player) => !player.eliminated_at) ??
        [],
);

const snapshotForm = useForm({
    recorded_on: new Date().toLocaleDateString('sv-SE'),
    chips: {} as Record<number, number>,
});

const activePlayersKey = computed(() =>
    activePlayers.value
        .map((player) => `${player.id}:${player.chips}`)
        .join('|'),
);

watch(
    activePlayersKey,
    () => {
        snapshotForm.chips = activePlayers.value.reduce<Record<number, number>>(
            (chips, player) => {
                chips[player.id] = player.chips;

                return chips;
            },
            {},
        );
    },
    { immediate: true },
);

const snapshotTotal = computed(() =>
    activePlayers.value.reduce(
        (total, player) => total + Number(snapshotForm.chips[player.id] ?? 0),
        0,
    ),
);

const snapshotMatches = computed(
    () =>
        props.activeRound !== null &&
        snapshotTotal.value === props.activeRound.total_chips,
);

const canSaveSnapshot = computed(
    () =>
        props.activeRound !== null &&
        activePlayers.value.length > 0 &&
        snapshotMatches.value,
);

const snapshotDays = computed(() => [
    ...new Set(
        (props.activeRound?.snapshots ?? []).map(
            (snapshot) => snapshot.recorded_on,
        ),
    ),
]);

const snapshotCell = (userId: number, day: string): string => {
    const snapshot = props.activeRound?.snapshots.find(
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

const submitSnapshot = () => {
    if (!props.activeRound || !canSaveSnapshot.value) {
        return;
    }

    snapshotForm.post(RoundController.storeSnapshot.url(props.activeRound.id), {
        preserveScroll: true,
    });
};

const createForm = useForm({
    total_chips: 1000,
    chips_per_player: 0,
    user_ids: props.users.map((user) => user.id),
});

const selectedPlayersCount = computed(() => createForm.user_ids.length);

const chipsPerPlayer = computed(() =>
    selectedPlayersCount.value > 0
        ? Math.floor(createForm.total_chips / selectedPlayersCount.value)
        : 0,
);

const chipsRemainder = computed(() =>
    selectedPlayersCount.value > 0
        ? createForm.total_chips % selectedPlayersCount.value
        : 0,
);

const canCreateRound = computed(
    () =>
        createForm.total_chips >= 1 &&
        selectedPlayersCount.value >= 2 &&
        chipsPerPlayer.value >= 1,
);

watch(
    () => [createForm.total_chips, createForm.user_ids.length],
    () => {
        createForm.chips_per_player = chipsPerPlayer.value;
    },
    { immediate: true },
);

const confirmCreateOpen = ref(false);

const submitCreate = () => {
    createForm
        .transform((data) => ({
            ...data,
            chips_per_player: chipsPerPlayer.value,
        }))
        .post(RoundController.store.url(), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
};

const requestCreate = () => {
    if (!canCreateRound.value) {
        return;
    }

    if (props.activeRound) {
        confirmCreateOpen.value = true;

        return;
    }

    submitCreate();
};

const confirmCreate = () => {
    confirmCreateOpen.value = false;
    submitCreate();
};

const closedRoundsCount = computed(
    () => props.rounds.filter((round) => round.status === 'encerrada').length,
);

const totalRoundsLabel = computed(
    () =>
        `${props.rounds.length} ${
            props.rounds.length === 1 ? 'rodada' : 'rodadas'
        } no total`,
);

const leaderWins = computed(() =>
    Math.max(0, ...props.ranking.map((entry) => entry.wins)),
);

const leaderCount = computed(
    () =>
        props.ranking.filter(
            (entry) => entry.wins === leaderWins.value && entry.wins > 0,
        ).length,
);

const isLeader = (entry: RankingEntry): boolean =>
    leaderWins.value > 0 && entry.wins === leaderWins.value;

const chipFormatter = new Intl.NumberFormat('pt-BR');
const percentFormatter = new Intl.NumberFormat('pt-BR', {
    maximumFractionDigits: 1,
});

const formatChips = (chips: number): string => chipFormatter.format(chips);
const formatPercent = (value: number): string =>
    `${percentFormatter.format(value)}%`;

const roundStatusLabel = (status: string): string =>
    status === 'ativa' ? 'Em andamento' : 'Encerrada';

const roundStatusClass = (status: string): string =>
    status === 'ativa'
        ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
        : 'border-border bg-muted text-muted-foreground';

const playerStatusClass = (eliminated: boolean): string =>
    eliminated
        ? 'border-destructive/20 bg-destructive/10 text-destructive'
        : 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';

const visitRound = (roundId: number) => {
    router.visit(RoundController.show.url(roundId));
};
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="relative isolate flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <Card class="py-0">
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <CalendarDays class="size-5" />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Rodada ativa
                        </p>
                        <p class="text-2xl leading-tight font-semibold">
                            {{ activeRound ? `#${activeRound.id}` : 'Nenhuma' }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            <span v-if="activeRound">
                                {{ formatChips(activeRound.total_chips) }}
                                fichas · desde {{ activeRound.starts_on }}
                            </span>
                            <span v-else>Abra a mesa.</span>
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="py-0">
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Users class="size-5" />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Jogadores ativos
                        </p>
                        <p class="text-2xl leading-tight font-semibold">
                            {{ activePlayers.length }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            O resto volta a trabalhar
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="py-0">
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Flag class="size-5" />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Rodadas encerradas
                        </p>
                        <p class="text-2xl leading-tight font-semibold">
                            {{ closedRoundsCount }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ totalRoundsLabel }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Atualizar rodada atual</CardTitle>
                    <CardDescription v-if="activeRound">
                        Registre as fichas de hoje.
                    </CardDescription>
                    <CardDescription v-else>
                        Sem rodada ativa. Abra a mesa ao lado.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form
                        v-if="activeRound"
                        class="space-y-4"
                        @submit.prevent="submitSnapshot"
                    >
                        <div class="flex flex-wrap items-end gap-4">
                            <div class="grid gap-2">
                                <Label for="recorded_on">Data</Label>
                                <input
                                    id="recorded_on"
                                    v-model="snapshotForm.recorded_on"
                                    type="date"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    :message="snapshotForm.errors.recorded_on"
                                />
                            </div>
                            <p
                                class="pb-2 text-sm"
                                :class="
                                    snapshotMatches
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-destructive'
                                "
                            >
                                Soma: {{ snapshotTotal }} /
                                {{ activeRound.total_chips }}
                            </p>
                        </div>

                        <div class="overflow-x-auto rounded-lg border">
                            <table class="w-full text-sm">
                                <thead
                                    class="bg-muted/50 text-left text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-3 py-2 font-medium">
                                            Jogador
                                        </th>
                                        <th class="px-3 py-2 font-medium">
                                            Fichas
                                        </th>
                                        <th class="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="player in activeRound.players"
                                        :key="player.id"
                                        class="border-t"
                                        :class="
                                            player.eliminated_at
                                                ? 'text-muted-foreground'
                                                : ''
                                        "
                                    >
                                        <td class="px-3 py-2">
                                            <span
                                                :class="
                                                    player.eliminated_at
                                                        ? 'line-through'
                                                        : ''
                                                "
                                            >
                                                {{ player.name }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-if="!player.eliminated_at"
                                                v-model.number="
                                                    snapshotForm.chips[
                                                        player.id
                                                    ]
                                                "
                                                type="number"
                                                inputmode="numeric"
                                                min="0"
                                                class="h-8 w-24 rounded-md border border-input bg-transparent px-2 text-right text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                            <span
                                                v-else
                                                class="text-muted-foreground"
                                            >
                                                —
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <Badge
                                                variant="outline"
                                                :class="
                                                    playerStatusClass(
                                                        Boolean(
                                                            player.eliminated_at,
                                                        ),
                                                    )
                                                "
                                            >
                                                {{
                                                    player.eliminated_at
                                                        ? 'Eliminado'
                                                        : 'Ativo'
                                                }}
                                            </Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <InputError :message="snapshotForm.errors.chips" />

                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <p class="text-xs text-muted-foreground">
                                Zero é eliminação automática.
                            </p>
                            <Button
                                type="submit"
                                :disabled="
                                    snapshotForm.processing || !canSaveSnapshot
                                "
                            >
                                Salvar dia
                            </Button>
                        </div>

                        <div
                            v-if="snapshotDays.length"
                            class="space-y-2 border-t pt-4"
                        >
                            <p class="text-sm font-medium">
                                Snapshots da rodada
                            </p>
                            <div class="overflow-x-auto rounded-lg border">
                                <table class="w-full text-sm">
                                    <thead
                                        class="bg-muted/50 text-left text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="px-3 py-2 font-medium">
                                                Jogador
                                            </th>
                                            <th
                                                v-for="day in snapshotDays"
                                                :key="day"
                                                class="px-3 py-2 text-right font-medium"
                                            >
                                                {{ formatDay(day) }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="player in activeRound.players"
                                            :key="player.id"
                                            class="border-t"
                                            :class="
                                                player.eliminated_at
                                                    ? 'text-muted-foreground'
                                                    : ''
                                            "
                                        >
                                            <td class="px-3 py-2">
                                                <span
                                                    :class="
                                                        player.eliminated_at
                                                            ? 'line-through'
                                                            : ''
                                                    "
                                                >
                                                    {{ player.name }}
                                                </span>
                                            </td>
                                            <td
                                                v-for="day in snapshotDays"
                                                :key="day"
                                                class="px-3 py-2 text-right"
                                            >
                                                {{
                                                    snapshotCell(player.id, day)
                                                }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </form>

                    <p v-else class="text-sm text-muted-foreground">
                        Crie a próxima rodada ao lado.
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Criar rodada</CardTitle>
                    <CardDescription v-if="activeRound">
                        A nova rodada encerra a #{{ activeRound.id }}.
                    </CardDescription>
                    <CardDescription v-else>
                        Defina o total e quem senta à mesa.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form class="space-y-4" @submit.prevent="requestCreate">
                        <div class="grid gap-2">
                            <Label for="total_chips">Total de fichas</Label>
                            <input
                                id="total_chips"
                                v-model.number="createForm.total_chips"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError
                                :message="createForm.errors.total_chips"
                            />
                        </div>

                        <div class="rounded-lg border bg-muted/30 p-3 text-sm">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <span
                                    v-if="selectedPlayersCount >= 2"
                                    class="font-medium"
                                >
                                    {{ chipsPerPlayer }} por jogador
                                </span>
                                <span v-else class="text-muted-foreground">
                                    Escolha pelo menos 2 jogadores
                                </span>
                                <span class="text-muted-foreground">
                                    {{ selectedPlayersCount }}
                                    {{
                                        selectedPlayersCount === 1
                                            ? 'selecionado'
                                            : 'selecionados'
                                    }}
                                </span>
                            </div>
                            <p
                                v-if="chipsRemainder"
                                class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                            >
                                {{ chipsRemainder }}
                                {{
                                    chipsRemainder === 1
                                        ? 'ficha vai'
                                        : 'fichas vão'
                                }}
                                sobrar sem dono.
                            </p>
                            <InputError
                                :message="createForm.errors.chips_per_player"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label>Quem vai jogar?</Label>
                            <div
                                class="grid max-h-56 gap-2 overflow-y-auto rounded-lg border p-3 sm:grid-cols-2"
                            >
                                <label
                                    v-for="user in users"
                                    :key="user.id"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        v-model="createForm.user_ids"
                                        :value="user.id"
                                        type="checkbox"
                                        class="size-4 rounded border-input accent-primary"
                                    />
                                    {{ user.name }}
                                </label>
                            </div>
                            <InputError :message="createForm.errors.user_ids" />
                        </div>

                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="
                                    createForm.processing || !canCreateRound
                                "
                            >
                                Criar rodada
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>

        <Card v-if="activeRound" class="gap-4">
            <CardHeader>
                <CardTitle>Evolução das fichas</CardTitle>
                <CardDescription>
                    A evolução do stack, dia após dia.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ChipTrendChart
                    :players="activeRound.players"
                    :snapshots="activeRound.snapshots"
                />
            </CardContent>
        </Card>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card id="historico" class="scroll-mt-24">
                <CardHeader>
                    <CardTitle>Histórico de rodadas</CardTitle>
                    <CardDescription>
                        Da primeira à última mão.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="rounds.length"
                        class="overflow-x-auto rounded-lg border"
                    >
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-medium">
                                        Rodada
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Início
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Jogadores
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Vencedor
                                    </th>
                                    <th class="px-3 py-2 font-medium">
                                        Status
                                    </th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="round in rounds"
                                    :key="round.id"
                                    class="cursor-pointer border-t transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                                    tabindex="0"
                                    role="link"
                                    @click="visitRound(round.id)"
                                    @keydown.enter="visitRound(round.id)"
                                >
                                    <td class="px-3 py-2 font-medium">
                                        #{{ round.id }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ round.starts_on ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{ round.players_count }}
                                    </td>
                                    <td class="px-3 py-2">
                                        {{
                                            round.winner ??
                                            (round.tie ? 'Empate' : '—')
                                        }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <Badge
                                            variant="outline"
                                            :class="
                                                roundStatusClass(round.status)
                                            "
                                        >
                                            {{ roundStatusLabel(round.status) }}
                                        </Badge>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <ChevronRight
                                            class="ml-auto size-4 text-muted-foreground"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhuma rodada ainda. Que coragem a de vocês.
                    </p>
                </CardContent>
            </Card>

            <Card id="ranking" class="scroll-mt-24">
                <CardHeader>
                    <CardTitle>Ranking de vitórias</CardTitle>
                    <CardDescription v-if="leaderWins > 0 && leaderCount > 1">
                        Empate no topo: {{ leaderCount }} jogadores com
                        {{ leaderWins }}
                        {{ leaderWins === 1 ? 'vitória' : 'vitórias' }}.
                    </CardDescription>
                    <CardDescription v-else>
                        Vitórias em rodadas encerradas.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="ranking.length"
                        class="overflow-x-auto rounded-lg border"
                    >
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-medium">#</th>
                                    <th class="px-3 py-2 font-medium">
                                        Jogador
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Vitórias
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Rodadas jogadas
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Taxa
                                    </th>
                                    <th
                                        class="px-3 py-2 text-right font-medium"
                                    >
                                        Sequência
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(entry, index) in ranking"
                                    :key="entry.id"
                                    class="border-t"
                                    :class="
                                        isLeader(entry) ? 'bg-primary/5' : ''
                                    "
                                >
                                    <td class="px-3 py-2 text-muted-foreground">
                                        {{ index + 1 }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <Trophy
                                                v-if="isLeader(entry)"
                                                class="size-3.5 text-primary"
                                            />
                                            {{ entry.name }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        {{ entry.wins }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        {{ entry.rounds_played }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        {{
                                            entry.win_rate === null
                                                ? '—'
                                                : formatPercent(entry.win_rate)
                                        }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <span class="whitespace-nowrap">
                                            {{
                                                entry.streak === 0
                                                    ? '–'
                                                    : entry.streak
                                            }}
                                            <span v-if="entry.streak >= 2">
                                                🔥
                                            </span>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Ninguém na mesa. Cadê o pessoal?
                    </p>
                </CardContent>
            </Card>
        </div>

        <Dialog v-if="activeRound" v-model:open="confirmCreateOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Encerrar a rodada #{{ activeRound.id }}?
                    </DialogTitle>
                    <DialogDescription>
                        Isso encerra a rodada #{{ activeRound.id }} e abre a
                        próxima. Continuar?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline">Cancelar</Button>
                    </DialogClose>
                    <Button @click="confirmCreate">Continuar</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
