<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    Flag,
    Trash2,
    Trophy,
    UserX,
    Users,
} from '@lucide/vue';
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

const form = useForm({
    recorded_on: new Date().toLocaleDateString('sv-SE'),
    chips: {} as Record<number, number>,
});

const activePlayersKey = computed(() =>
    activePlayers.value
        .map((player) => `${player.id}:${player.pivot.chips}`)
        .join('|'),
);

watch(
    activePlayersKey,
    () => {
        form.chips = activePlayers.value.reduce<Record<number, number>>(
            (chips, player) => {
                chips[player.id] = player.pivot.chips;

                return chips;
            },
            {},
        );
    },
    { immediate: true },
);

const sum = computed(() =>
    activePlayers.value.reduce(
        (total, player) => total + Number(form.chips[player.id] ?? 0),
        0,
    ),
);

const sumMatches = computed(() => sum.value === props.round.total_chips);

const isActive = computed(() => props.round.status === 'ativa');

const canSave = computed(
    () => isActive.value && activePlayers.value.length > 0 && sumMatches.value,
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

const eliminatedCount = computed(
    () => props.round.players.length - activePlayers.value.length,
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
        chips: topChips,
        tied: leaders.length > 1,
        count: leaders.length,
    };
});

const lastSnapshotOn = computed(() => {
    const last = days.value[days.value.length - 1];

    return last ? formatDay(last) : null;
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

const submit = () => {
    if (!isActive.value) {
        return;
    }

    form.post(RoundController.storeSnapshot.url(props.round.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Rodada ${round.id}`" />

    <div
        class="relative isolate flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">
                        Rodada #{{ round.id }}
                    </h1>
                    <Badge variant="outline" :class="statusClass">
                        {{ statusLabel }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    Total de fichas: {{ formatChips(round.total_chips) }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="dashboard()">Voltar ao dashboard</Link>
                </Button>

                <template v-if="canManage">
                    <Button
                        v-if="isActive"
                        type="button"
                        variant="outline"
                        @click="closeOpen = true"
                    >
                        <Flag class="size-4" />
                        Encerrar rodada
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="deleteOpen = true"
                    >
                        <Trash2 class="size-4" />
                        Excluir rodada
                    </Button>
                </template>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Card class="py-0">
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <Trophy class="size-5" />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Líder
                        </p>
                        <p class="truncate text-2xl leading-tight font-semibold">
                            {{ leader?.name ?? '—' }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{
                                leader
                                    ? `${formatChips(leader.chips)} fichas`
                                    : 'Sem jogadores ativos'
                            }}
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
                            Ativos
                        </p>
                        <p class="text-2xl leading-tight font-semibold">
                            {{ activePlayers.length }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            Jogadores na mesa
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="py-0">
                <CardContent class="flex items-center gap-4 p-4">
                    <div
                        class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary"
                    >
                        <UserX class="size-5" />
                    </div>
                    <div class="min-w-0 space-y-0.5">
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Eliminados
                        </p>
                        <p class="text-2xl leading-tight font-semibold">
                            {{ eliminatedCount }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            Fora da rodada
                        </p>
                    </div>
                </CardContent>
            </Card>

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
                            Dias registrados
                        </p>
                        <p class="text-2xl leading-tight font-semibold">
                            {{ days.length }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{
                                lastSnapshotOn
                                    ? `Último registro: ${lastSnapshotOn}`
                                    : 'Nenhum lançamento'
                            }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

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
                        Atualize as fichas dos jogadores que continuam na
                        rodada.
                    </CardDescription>
                    <CardDescription v-else-if="isActive && activePlayers.length == 0">
                        Todos os jogadores desta rodada foram eliminados.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <form
                        v-if="canManage && isActive && activePlayers.length"
                        class="space-y-4"
                        @submit.prevent="submit"
                    >
                        <div class="flex flex-wrap items-end gap-4">
                            <div class="grid gap-2">
                                <Label for="recorded_on">Data</Label>
                                <input
                                    id="recorded_on"
                                    v-model="form.recorded_on"
                                    type="date"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                />
                                <InputError
                                    :message="form.errors.recorded_on"
                                />
                            </div>
                            <p
                                class="pb-2 text-sm"
                                :class="
                                    sumMatches
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-destructive'
                                "
                            >
                                Soma: {{ sum }} / {{ round.total_chips }}
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
                                        <td class="px-3 py-2">
                                            <span
                                                :class="
                                                    player.pivot.eliminated_at
                                                        ? 'line-through'
                                                        : ''
                                                "
                                            >
                                                {{ player.name }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2">
                                            <input
                                                v-if="
                                                    !player.pivot.eliminated_at
                                                "
                                                v-model.number="
                                                    form.chips[player.id]
                                                "
                                                type="number"
                                                inputmode="numeric"
                                                min="0"
                                                class="h-8 w-24 rounded-md border border-input bg-transparent px-2 text-center text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                            />
                                            <Badge
                                                v-else
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
                                {{
                                    sumMatches
                                        ? 'Zero é eliminação automática. Sem choro.'
                                        : 'A soma precisa fechar antes de salvar.'
                                }}
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
                    <CardTitle>Histórico de snapshots</CardTitle>
                    <CardDescription>
                        Fichas de cada jogador por dia.
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <div
                        v-if="days.length"
                        class="overflow-x-auto rounded-lg border"
                    >
                        <table class="w-full text-sm">
                            <thead
                                class="bg-muted/50 text-left text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-3 py-2 font-medium">
                                        Jogador
                                    </th>
                                    <th
                                        v-for="day in days"
                                        :key="day"
                                        class="px-3 py-2 text-center font-medium"
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
                                    <td class="px-3 py-2">
                                        <span
                                            :class="
                                                player.pivot.eliminated_at
                                                    ? 'line-through'
                                                    : ''
                                            "
                                        >
                                            {{ player.name }}
                                        </span>
                                    </td>
                                    <td
                                        v-for="day in days"
                                        :key="day"
                                        class="px-3 py-2 text-center"
                                    >
                                        {{ cell(player.id, day) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-else class="text-sm text-muted-foreground">
                        Nenhum dia registrado. O histórico não se escreve
                        sozinho.
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card class="gap-4">
            <CardHeader>
                <CardTitle>Evolução das fichas</CardTitle>
                <CardDescription>
                    Como cada stack se mexeu ao longo dos dias. As linhas não
                    mentem.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ChipTrendChart
                    :players="round.players"
                    :snapshots="snapshots"
                />
            </CardContent>
        </Card>

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
