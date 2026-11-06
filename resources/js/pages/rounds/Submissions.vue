<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Inbox } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import RoundController from '@/actions/App/Http/Controllers/RoundController';
import InputError from '@/components/InputError.vue';
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
import { show as roundShow } from '@/routes/rounds';
import { index as submissionsIndex } from '@/routes/rounds/submissions';

type Player = {
    id: number;
    name: string;
    avatar: string | null;
    pivot: { chips: number; eliminated_at: string | null };
};

type Submission = {
    user_id: number;
    chips: number;
    chip_counts: Record<string, number> | null;
    source: string;
    submitted_at: string | null;
};

const props = defineProps<{
    round: {
        id: number;
        starts_on: string;
        status: string;
        total_chips: number;
        players: Player[];
    };
    dates: string[];
    selectedDate: string;
    submissions: Submission[];
}>();

defineOptions({
    layout: {
        transparentHeader: true,
        backdrop: true,
    },
});

const chipDenominations = [100, 50, 25, 10, 5] as const;

const activePlayers = computed(() =>
    props.round.players.filter((player) => !player.pivot.eliminated_at),
);

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

const chipCountsTotal = (counts?: Record<number, number>): number =>
    chipDenominations.reduce(
        (total, value) => total + (counts?.[value] ?? 0) * value,
        0,
    );

const parseChipAmount = (value: string): number => {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? Math.max(0, Math.trunc(parsed)) : 0;
};

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

const form = useForm({
    recorded_on: props.selectedDate,
    chips: {} as Record<number, number>,
});

const chipCounts = ref<Record<number, Record<number, number>>>({});

const selectedDateDisplay = ref(isoToDisplay(props.selectedDate));

const dateInput = ref<HTMLInputElement | null>(null);

const openDatePicker = (): void => {
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

const submissionFor = (userId: number): Submission | undefined =>
    props.submissions.find((submission) => submission.user_id === userId);

const initialCounts = (player: Player): Record<number, number> => {
    const submission = submissionFor(player.id);

    if (submission?.chip_counts) {
        return chipDenominations.reduce<Record<number, number>>(
            (counts, value) => {
                counts[value] = Number(
                    submission.chip_counts?.[String(value)] ?? 0,
                );

                return counts;
            },
            emptyChipCounts(),
        );
    }

    return chipCountsFromTotal(Number(player.pivot.chips));
};

const initializationKey = computed(
    () =>
        `${props.selectedDate}|${activePlayers.value
            .map((player) => `${player.id}:${player.pivot.chips}`)
            .join(',')}|${props.submissions
            .map(
                (submission) =>
                    `${submission.user_id}:${submission.chips}:${JSON.stringify(submission.chip_counts)}`,
            )
            .join(',')}`,
);

watch(
    initializationKey,
    () => {
        const counts: Record<number, Record<number, number>> = {};
        const chips: Record<number, number> = {};

        for (const player of activePlayers.value) {
            const playerCounts = initialCounts(player);

            counts[player.id] = playerCounts;
            chips[player.id] = chipCountsTotal(playerCounts);
        }

        chipCounts.value = counts;
        form.chips = chips;
        form.recorded_on = props.selectedDate;
    },
    { immediate: true },
);

const playerChipTotal = (userId: number): number =>
    chipCountsTotal(chipCounts.value[userId]);

const playerTotal = (userId: number): number =>
    Number(form.chips[userId] ?? 0) || 0;

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

const sum = computed(() =>
    activePlayers.value.reduce(
        (total, player) => total + playerTotal(player.id),
        0,
    ),
);

const sumMatches = computed(() => sum.value === props.round.total_chips);

const pendingCount = computed(
    () =>
        activePlayers.value.filter((player) => submissionFor(player.id)).length,
);

const canRegister = computed(
    () =>
        props.round.status === 'ativa' &&
        activePlayers.value.length > 0 &&
        sumMatches.value,
);

const chipFormatter = new Intl.NumberFormat('pt-BR');
const formatChips = (chips: number): string => chipFormatter.format(chips);

const formatDay = (day: string): string => {
    const [, month, date] = day.split('-');

    return month && date ? `${date}/${month}` : day;
};

const formatTime = (value: string | null): string | null => {
    if (!value) {
        return null;
    }

    return new Date(value).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const sourceLabel = (userId: number): string => {
    const submission = submissionFor(userId);

    if (!submission) {
        return 'Não enviou';
    }

    const time = formatTime(submission.submitted_at);

    return `${submission.source === 'discord' ? 'Discord' : 'Admin'}${time ? ` · ${time}` : ''}`;
};

watch(
    () => props.selectedDate,
    (value) => {
        selectedDateDisplay.value = isoToDisplay(value);
    },
);

const navigateToDate = (date: string): void => {
    if (!date || date === props.selectedDate) {
        return;
    }

    router.get(
        submissionsIndex.url(props.round.id, { query: { date } }),
        {},
        { preserveScroll: true, preserveState: true },
    );
};

const onDateInput = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    const formatted = maskDate(target.value);

    selectedDateDisplay.value = formatted;
    target.value = formatted;

    const date = displayToIso(formatted);

    if (date) {
        navigateToDate(date);
    }
};

const onDatePicked = (event: Event): void => {
    const target = event.target as HTMLInputElement;

    selectedDateDisplay.value = isoToDisplay(target.value);
    navigateToDate(target.value);
};

const applyDisplayedDate = (): void => {
    const date = displayToIso(selectedDateDisplay.value);

    if (date) {
        navigateToDate(date);
    }
};

const submit = (): void => {
    if (!canRegister.value) {
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
    <Head :title="`Envios da rodada ${round.id}`" />

    <div
        class="relative isolate flex h-full flex-1 flex-col gap-5 overflow-x-auto p-4 md:p-6"
    >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-1">
                <p
                    class="text-xs font-medium tracking-[0.18em] text-primary uppercase"
                >
                    Rodada #{{ round.id }}
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <h1
                        class="text-2xl font-semibold tracking-tight md:text-3xl"
                    >
                        Envios pendentes
                    </h1>
                    <Badge variant="outline">
                        <Inbox class="mr-1 size-3.5" />
                        {{ pendingCount }} de {{ activePlayers.length }}
                    </Badge>
                </div>
                <p class="text-sm text-muted-foreground">
                    Revise as fichas recebidas pelo Discord, ajuste o que for
                    necessário e registre o dia.
                </p>
            </div>

            <Button as-child variant="outline">
                <Link :href="roundShow(round.id)">
                    <ArrowLeft class="size-4" />
                    Voltar para a rodada
                </Link>
            </Button>
        </header>

        <Card>
            <CardHeader>
                <CardTitle>Registrar o dia</CardTitle>
                <CardDescription>
                    A soma precisa fechar em
                    {{ formatChips(round.total_chips) }} fichas para registrar.
                </CardDescription>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1">
                        <div
                            class="relative flex h-9 w-fit items-center overflow-hidden rounded-md border border-input shadow-xs focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50"
                        >
                            <input
                                id="recorded_on"
                                v-model="selectedDateDisplay"
                                type="text"
                                inputmode="numeric"
                                autocomplete="off"
                                maxlength="10"
                                placeholder="DD/MM/AAAA"
                                aria-label="Data do registro"
                                class="h-full w-32 bg-transparent pr-2 pl-3 text-sm outline-none placeholder:text-muted-foreground"
                                @input="onDateInput"
                                @blur="applyDisplayedDate"
                                @keydown.enter.prevent="applyDisplayedDate"
                            />
                            <input
                                ref="dateInput"
                                type="date"
                                tabindex="-1"
                                aria-hidden="true"
                                :value="selectedDate"
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
                    </div>

                    <div
                        v-if="dates.length"
                        class="flex flex-wrap items-center gap-1 pb-2 text-xs text-muted-foreground"
                    >
                        <span>Com envios:</span>
                        <Link
                            v-for="day in dates"
                            :key="day"
                            :href="
                                submissionsIndex.url(round.id, {
                                    query: { date: day },
                                })
                            "
                            class="rounded-md border px-2 py-1 tabular-nums transition-colors hover:bg-muted"
                            :class="
                                day === selectedDate
                                    ? 'border-primary/40 bg-primary/10 text-foreground'
                                    : ''
                            "
                        >
                            {{ formatDay(day) }}
                        </Link>
                    </div>
                </div>
            </CardHeader>

            <CardContent>
                <form
                    v-if="round.status === 'ativa' && activePlayers.length"
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
                                        class="px-2 py-2.5 text-center font-medium"
                                    >
                                        Atual
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
                                    <th class="px-3 py-2.5 font-medium">
                                        Origem
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="player in activePlayers"
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
                                            {{ player.name }}
                                        </span>
                                    </td>
                                    <td
                                        class="px-2 py-2.5 text-center text-muted-foreground tabular-nums"
                                    >
                                        {{ formatChips(player.pivot.chips) }}
                                    </td>
                                    <td
                                        v-for="value in chipDenominations"
                                        :key="value"
                                        class="px-1 py-2.5 text-center"
                                    >
                                        <input
                                            v-model.number="
                                                chipCounts[player.id][value]
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
                                                onTotalInput(player.id, $event)
                                            "
                                        />
                                        <p
                                            v-if="
                                                playerChipTotal(player.id) !==
                                                playerTotal(player.id)
                                            "
                                            class="mt-1 text-[10px] text-muted-foreground tabular-nums"
                                        >
                                            Contagem:
                                            {{
                                                formatChips(
                                                    playerChipTotal(player.id),
                                                )
                                            }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-3 py-2.5 text-xs whitespace-nowrap"
                                        :class="
                                            submissionFor(player.id)
                                                ? 'text-muted-foreground'
                                                : 'text-destructive'
                                        "
                                    >
                                        {{ sourceLabel(player.id) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <InputError :message="form.errors.chips" />
                    <InputError :message="form.errors.recorded_on" />

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
                                · precisa fechar antes de registrar
                            </span>
                        </p>
                        <Button
                            type="submit"
                            :disabled="form.processing || !canRegister"
                        >
                            Registrar o dia
                        </Button>
                    </div>
                </form>

                <p
                    v-else-if="round.status !== 'ativa'"
                    class="text-sm text-muted-foreground"
                >
                    Esta rodada está encerrada e não pode receber registros.
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    Não há jogadores ativos nesta rodada.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
