<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronDown,
    ChevronRight,
    ChevronUp,
    Coins,
    Flag,
    Trophy,
    Users,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PlayerController from '@/actions/App/Http/Controllers/Admin/PlayerController';
import RoundController from '@/actions/App/Http/Controllers/RoundController';
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

type ActiveRound = {
    id: number;
    starts_on: string | null;
    total_chips: number;
    players_count: number;
    active_players_count: number;
    last_snapshot_on: string | null;
};

type Stats = {
    total_players: number;
    total_rounds: number;
    closed_rounds: number;
    chips_in_play: number;
    last_snapshot_on: string | null;
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
    canManage: boolean;
    users: { id: number; name: string }[];
    stats: Stats;
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

const isAdmin = computed(() => props.canManage);

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

const showAllHistory = ref(false);
const visibleHistoryRounds = computed(() =>
    showAllHistory.value ? props.rounds : props.rounds.slice(0, 5),
);
const hasMoreHistoryRounds = computed(() => props.rounds.length > 5);

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

const visitRound = (roundId: number) => {
    router.visit(RoundController.show.url(roundId));
};
</script>

<template>
    <Head title="Dashboard" />

    <div
        class="relative isolate flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4"
    >
        <div class="grid auto-rows-min gap-4 md:grid-cols-2 xl:grid-cols-4">
            <component
                :is="activeRound ? Link : 'div'"
                v-bind="
                    activeRound
                        ? { href: RoundController.show.url(activeRound.id) }
                        : {}
                "
                class="block"
            >
                <Card
                    class="h-full py-0 transition-colors"
                    :class="activeRound ? 'hover:bg-muted/40' : ''"
                >
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
                                {{
                                    activeRound
                                        ? `#${activeRound.id}`
                                        : 'Nenhuma'
                                }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    activeRound
                                        ? `${formatChips(activeRound.total_chips)} fichas · ${activeRound.active_players_count} ativos`
                                        : 'Abra a mesa.'
                                }}
                            </p>
                            <p
                                v-if="activeRound?.last_snapshot_on"
                                class="truncate text-xs text-muted-foreground"
                            >
                                Último lançamento:
                                {{ activeRound.last_snapshot_on }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </component>

            <component
                :is="isAdmin ? Link : 'div'"
                v-bind="isAdmin ? { href: PlayerController.index.url() } : {}"
                class="block"
            >
                <Card
                    class="h-full py-0 transition-colors"
                    :class="isAdmin ? 'hover:bg-muted/40' : ''"
                >
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
                                Jogadores
                            </p>
                            <p class="text-2xl leading-tight font-semibold">
                                {{ stats.total_players }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    activeRound
                                        ? `${activeRound.active_players_count} ativos na mesa`
                                        : 'Nenhum jogador ativo'
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </component>

            <component
                :is="activeRound ? Link : 'div'"
                v-bind="
                    activeRound
                        ? { href: RoundController.show.url(activeRound.id) }
                        : {}
                "
                class="block"
            >
                <Card
                    class="h-full py-0 transition-colors"
                    :class="activeRound ? 'hover:bg-muted/40' : ''"
                >
                    <CardContent class="flex items-center gap-4 p-4">
                        <div
                            class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                        >
                            <Coins class="size-5" />
                        </div>
                        <div class="min-w-0 space-y-0.5">
                            <p
                                class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Fichas em jogo
                            </p>
                            <p class="text-2xl leading-tight font-semibold">
                                {{
                                    activeRound
                                        ? formatChips(stats.chips_in_play)
                                        : '—'
                                }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    activeRound
                                        ? `Total da rodada #${activeRound.id}`
                                        : 'Nenhuma rodada ativa'
                                }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </component>

            <a href="#historico" class="block">
                <Card class="h-full py-0 transition-colors hover:bg-muted/40">
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
                                Rodadas
                            </p>
                            <p class="text-2xl leading-tight font-semibold">
                                {{ stats.total_rounds }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ stats.closed_rounds }} encerradas
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                Atualizado em: {{ stats.last_snapshot_on ?? '—' }}
                            </p>
                        </div>
                    </CardContent>
                </Card>
            </a>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <Card v-if="canManage" class="xl:col-span-2">
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

            <Card v-else class="xl:col-span-2">
                <CardHeader>
                    <CardTitle>Modo jogador</CardTitle>
                    <CardDescription>
                        Apenas o administrador pode criar rodadas e lançar
                        fichas. Você pode acompanhar o ranking e o histórico
                        normalmente.
                    </CardDescription>
                </CardHeader>
            </Card>
        </div>
        <div class="grid gap-4 xl:grid-cols-2">
            

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
                                        class="px-3 py-2 text-center font-medium"
                                    >
                                        Vitórias
                                    </th>
                                    <th
                                        class="px-3 py-2 text-center font-medium"
                                    >
                                        Rodadas jogadas
                                    </th>
                                    <th
                                        class="px-3 py-2 text-center font-medium"
                                    >
                                        Taxa
                                    </th>
                                    <th
                                        class="px-3 py-2 text-center font-medium"
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
                                    <td class="px-3 py-2 text-center">
                                        {{ entry.wins }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        {{ entry.rounds_played }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        {{
                                            entry.win_rate === null
                                                ? '—'
                                                : formatPercent(entry.win_rate)
                                        }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
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

            <Card id="historico" class="scroll-mt-24">
                <CardHeader>
                    <CardTitle>Histórico de rodadas</CardTitle>
                    <CardDescription>
                        Da primeira à última mão.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <template v-if="rounds.length">
                        <div class="overflow-x-auto rounded-lg border">
                            <table class="w-full text-sm">
                                <thead
                                    class="bg-muted/50 text-left text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium">
                                            Rodada
                                        </th>
                                        <th class="px-3 py-2 text-center font-medium">
                                            Início
                                        </th>
                                        <th class="px-3 py-2 text-center font-medium">
                                            Jogadores
                                        </th>
                                        <th class="px-3 py-2 text-center font-medium">
                                            Vencedor
                                        </th>
                                        <th class="px-3 py-2 text-center font-medium">
                                            Status
                                        </th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="round in visibleHistoryRounds"
                                        :key="round.id"
                                        class="cursor-pointer border-t transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                                        tabindex="0"
                                        role="link"
                                        @click="visitRound(round.id)"
                                        @keydown.enter="visitRound(round.id)"
                                    >
                                        <td class="px-3 py-2 text-left font-medium">
                                            #{{ round.id }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            {{ round.starts_on ?? '—' }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            {{ round.players_count }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            {{
                                                round.winner ??
                                                (round.tie ? 'Empate' : '—')
                                            }}
                                        </td>
                                        <td class="px-3 py-2 text-center">
                                            <Badge
                                                variant="outline"
                                                :class="
                                                    roundStatusClass(
                                                        round.status,
                                                    )
                                                "
                                            >
                                                {{
                                                    roundStatusLabel(
                                                        round.status,
                                                    )
                                                }}
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

                        <div
                            v-if="hasMoreHistoryRounds"
                            class="mt-3 flex justify-center"
                        >
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :aria-expanded="showAllHistory"
                                @click="showAllHistory = !showAllHistory"
                            >
                                <component
                                    :is="
                                        showAllHistory ? ChevronUp : ChevronDown
                                    "
                                />
                                {{ showAllHistory ? 'Ver menos' : 'Ver mais' }}
                            </Button>
                        </div>
                    </template>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhuma rodada ainda. Que coragem a de vocês.
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
