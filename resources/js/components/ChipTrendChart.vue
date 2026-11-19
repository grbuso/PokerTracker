<script setup lang="ts">
import { Maximize2 } from '@lucide/vue';
import { computed, ref, withDefaults } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { getInitials } from '@/composables/useInitials';
import { formatShortDate, toDateKey } from '@/lib/dates';

defineOptions({
    name: 'ChipTrendChart',
});

type ChartPlayer = {
    id: number;
    name: string;
    avatar: string | null;
};

type ChartSnapshot = {
    user_id: number;
    recorded_on: string;
    chips: number;
};

type ChartPoint = {
    x: number;
    y: number;
    day: string;
    chips: number;
};

type HoveredPoint = ChartPoint & {
    playerId: number;
    name: string;
    color: string;
};

const props = withDefaults(
    defineProps<{
        players: ChartPlayer[];
        snapshots: ChartSnapshot[];
        expandable?: boolean;
        large?: boolean;
    }>(),
    {
        expandable: true,
        large: false,
    },
);

const width = computed(() => (props.large ? 1400 : 720));
const height = computed(() => (props.large ? 620 : 240));
const padding = computed(() =>
    props.large
        ? { top: 28, right: 36, bottom: 60, left: 88 }
        : { top: 16, right: 16, bottom: 30, left: 52 },
);

const chartOpen = ref(false);

const colors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--chart-6)',
    'var(--chart-7)',
    'var(--chart-8)',
    'var(--chart-9)',
];

const chipFormatter = new Intl.NumberFormat('pt-BR');

const formatChips = (chips: number): string => chipFormatter.format(chips);

const formatCompact = (value: number): string => {
    if (value >= 10000) {
        const k = value / 1000;
        const rounded = Math.round(k * 10) / 10;

        return `${rounded % 1 === 0 ? rounded.toFixed(0) : rounded.toFixed(1)}k`;
    }

    return formatChips(value);
};

const days = computed(() =>
    [
        ...new Set(
            props.snapshots.map((snapshot) => toDateKey(snapshot.recorded_on)),
        ),
    ].sort(),
);

const chartPlayers = computed(() =>
    props.players.filter((player) =>
        props.snapshots.some((snapshot) => snapshot.user_id === player.id),
    ),
);

const niceStep = (value: number): number => {
    if (value <= 1) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(value));
    const normalized = value / magnitude;
    const step =
        normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;

    return step * magnitude;
};

const maxChips = computed(() => {
    const highest = Math.max(
        1,
        ...props.snapshots.map((snapshot) => snapshot.chips),
    );
    const axisMax = Math.max(highest, 4);
    const step = niceStep(axisMax / 4);

    return Math.max(step, Math.ceil(axisMax / step) * step);
});

const ticks = computed(() =>
    Array.from({ length: 5 }, (_, index) => (maxChips.value / 4) * index),
);

const plotWidth = computed(
    () => width.value - padding.value.left - padding.value.right,
);
const plotHeight = computed(
    () => height.value - padding.value.top - padding.value.bottom,
);

const xFor = (index: number): number => {
    if (days.value.length <= 1) {
        return padding.value.left + plotWidth.value / 2;
    }

    return (
        padding.value.left + (index / (days.value.length - 1)) * plotWidth.value
    );
};

const yFor = (chips: number): number =>
    padding.value.top + (1 - chips / maxChips.value) * plotHeight.value;

const showDayLabel = (index: number): boolean => {
    const n = days.value.length;

    if (n <= 7) {
        return true;
    }

    const step = Math.ceil(n / 6);

    return index % step === 0 || index === n - 1;
};

const smoothPath = (points: ChartPoint[]): string => {
    if (points.length === 0) {
        return '';
    }

    if (points.length === 1) {
        return `M ${points[0].x} ${points[0].y}`;
    }

    if (points.length === 2) {
        return `M ${points[0].x} ${points[0].y} L ${points[1].x} ${points[1].y}`;
    }

    let d = `M ${points[0].x} ${points[0].y}`;

    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i - 1] ?? points[i];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2] ?? p2;

        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;

        d += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`;
    }

    return d;
};

const series = computed(() => {
    const dayIndexes = new Map(days.value.map((day, index) => [day, index]));

    const items = chartPlayers.value.map((player, playerIndex) => {
        const points = props.snapshots
            .filter((snapshot) => snapshot.user_id === player.id)
            .sort((first, second) =>
                first.recorded_on.localeCompare(second.recorded_on),
            )
            .map((snapshot) => {
                const dayIndex =
                    dayIndexes.get(toDateKey(snapshot.recorded_on)) ?? 0;

                return {
                    x: xFor(dayIndex),
                    y: yFor(snapshot.chips),
                    day: toDateKey(snapshot.recorded_on),
                    chips: snapshot.chips,
                };
            });

        const previousByDay = new Map<string, number>();

        for (let i = 1; i < points.length; i++) {
            previousByDay.set(points[i].day, points[i - 1].chips);
        }

        return {
            id: player.id,
            name: player.name,
            avatar: player.avatar,
            color: colors[playerIndex % colors.length],
            points,
            path: smoothPath(points),
            last: points.at(-1)?.chips ?? null,
            previousByDay,
        };
    });

    return items.sort((a, b) => (b.last ?? -1) - (a.last ?? -1));
});

const highlightedId = ref<number | null>(null);
const pinnedId = ref<number | null>(null);
const hoveredPoint = ref<HoveredPoint | null>(null);

const activeId = computed(() => pinnedId.value ?? highlightedId.value);

const isDimmed = (id: number): boolean =>
    activeId.value !== null && activeId.value !== id;

const seriesOpacity = (id: number): number => (isDimmed(id) ? 0.12 : 1);

const seriesStrokeWidth = (id: number): number =>
    props.large
        ? activeId.value === id
            ? 4.5
            : 3
        : activeId.value === id
          ? 2.75
          : 2;

const axisFontSize = computed(() => (props.large ? 16 : 10));
const pointRadius = computed(() => (props.large ? 5 : 3));
const hoverPointRadius = computed(() => (props.large ? 7 : 4.5));
const hoverRingRadius = computed(() => (props.large ? 9 : 6));
const hitRadius = computed(() => (props.large ? 18 : 12));
const tooltipNameFontSize = computed(() => (props.large ? 16 : 11));
const tooltipDetailFontSize = computed(() => (props.large ? 13 : 10));
const tooltipNameX = computed(() => (props.large ? 30 : 20));
const tooltipNameY = computed(() => (props.large ? 27 : 17));
const tooltipDetailX = computed(() => (props.large ? 18 : 12));
const tooltipDetailY = computed(() => (props.large ? 56 : 34));
const tooltipDotX = computed(() => (props.large ? 18 : 12));
const tooltipDotY = computed(() => (props.large ? 24 : 14));
const tooltipDotRadius = computed(() => (props.large ? 5 : 3));
const legendAvatarClass = computed(() => (props.large ? 'size-8' : 'size-5'));
const legendFallbackClass = computed(() =>
    props.large ? 'text-xs' : 'text-[9px]',
);
const legendDotClass = computed(() => (props.large ? 'size-3' : 'size-2'));

const togglePin = (id: number): void => {
    pinnedId.value = pinnedId.value === id ? null : id;
};

const showPoint = (
    item: { id: number; name: string; color: string },
    point: ChartPoint,
): void => {
    hoveredPoint.value = {
        ...point,
        playerId: item.id,
        name: item.name,
        color: item.color,
    };
    highlightedId.value = item.id;
};

const clearHover = (): void => {
    hoveredPoint.value = null;
    highlightedId.value = null;
};

const isPointHovered = (playerId: number, day: string): boolean =>
    hoveredPoint.value?.playerId === playerId && hoveredPoint.value.day === day;

const truncate = (value: string, maxLength: number): string =>
    value.length > maxLength ? `${value.slice(0, maxLength - 1)}…` : value;

const tooltipName = computed(() =>
    hoveredPoint.value ? truncate(hoveredPoint.value.name, 28) : '',
);

const deltaText = computed(() => {
    const point = hoveredPoint.value;

    if (!point) {
        return '';
    }

    const item = series.value.find((entry) => entry.id === point.playerId);
    const previous = item?.previousByDay.get(point.day);

    if (previous === undefined) {
        return '';
    }

    const diff = point.chips - previous;

    if (diff === 0) {
        return ' · sem mudança';
    }

    const sign = diff > 0 ? '+' : '-';

    return ` · ${sign}${formatChips(Math.abs(diff))}`;
});

const tooltipDetail = computed(() => {
    const point = hoveredPoint.value;

    return point
        ? `${formatShortDate(point.day)} · ${formatChips(point.chips)} fichas${deltaText.value}`
        : '';
});

const tooltipHeight = computed(() => (props.large ? 76 : 44));

const tooltipWidth = computed(() => {
    const nameWidth =
        tooltipName.value.length * (props.large ? 9.6 : 6.2) +
        (props.large ? 44 : 24);
    const detailWidth =
        tooltipDetail.value.length * (props.large ? 7.8 : 5.1) +
        (props.large ? 36 : 20);

    return Math.min(
        props.large ? 480 : 260,
        Math.max(props.large ? 210 : 110, nameWidth, detailWidth),
    );
});

const tooltipPosition = computed(() => {
    const point = hoveredPoint.value;

    if (!point) {
        return { x: 0, y: 0 };
    }

    const boxWidth = tooltipWidth.value;
    const boxHeight = tooltipHeight.value;
    const gap = props.large ? 14 : 10;
    const above = point.y - boxHeight - gap >= padding.value.top;
    const y = above ? point.y - boxHeight - gap : point.y + gap;
    const maxX = width.value - padding.value.right - boxWidth;
    const x = Math.min(
        Math.max(point.x - boxWidth / 2, padding.value.left),
        maxX,
    );

    return { x, y };
});
</script>

<template>
    <div>
        <div
            v-if="!series.length"
            class="flex h-48 items-center justify-center rounded-lg border border-dashed text-sm text-muted-foreground"
        >
            Sem snapshots ainda. Salve o primeiro dia e as linhas aparecem.
        </div>

        <div v-else class="space-y-3">
            <div
                class="group relative rounded-xl border bg-card/50 p-2 sm:p-3"
                @mouseleave="highlightedId = null"
            >
                <button
                    v-if="expandable"
                    type="button"
                    class="absolute top-2 right-2 z-10 flex size-8 items-center justify-center rounded-md border bg-background/85 text-muted-foreground opacity-0 shadow-sm backdrop-blur transition-opacity hover:text-foreground focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring group-hover:opacity-100 group-focus-within:opacity-100"
                    aria-label="Ampliar gráfico de evolução"
                    title="Ampliar gráfico"
                    @click="chartOpen = true"
                >
                    <Maximize2 class="size-4" />
                </button>

                <svg
                    :viewBox="`0 0 ${width} ${height}`"
                    class="h-auto w-full"
                    role="img"
                    aria-label="Evolução das fichas por jogador"
                >
                    <g>
                        <line
                            :x1="padding.left"
                            :x2="width - padding.right"
                            :y1="height - padding.bottom"
                            :y2="height - padding.bottom"
                            stroke="var(--border)"
                        />
                        <line
                            v-for="tick in ticks"
                            :key="`grid-${tick}`"
                            :x1="padding.left"
                            :x2="width - padding.right"
                            :y1="yFor(tick)"
                            :y2="yFor(tick)"
                            :stroke="tick === 0 ? 'var(--border)' : 'var(--border)'"
                            :stroke-dasharray="tick === 0 ? undefined : '4 4'"
                            stroke-opacity="0.8"
                        />
                        <text
                            v-for="tick in ticks"
                            :key="`tick-${tick}`"
                            :x="padding.left - 8"
                            :y="yFor(tick) + 3"
                            text-anchor="end"
                            :font-size="axisFontSize"
                            class="fill-muted-foreground tabular-nums"
                        >
                            {{ formatCompact(tick) }}
                        </text>
                    </g>

                    <g>
                        <template v-for="(day, index) in days" :key="`day-${day}`">
                            <line
                                :x1="xFor(index)"
                                :x2="xFor(index)"
                                :y1="height - padding.bottom"
                                :y2="height - padding.bottom + 4"
                                stroke="var(--border)"
                            />
                            <text
                                v-if="showDayLabel(index)"
                                :x="xFor(index)"
                                :y="height - 8"
                                text-anchor="middle"
                                :font-size="axisFontSize"
                                class="fill-muted-foreground"
                            >
                                {{ formatShortDate(day) }}
                            </text>
                        </template>
                    </g>

                    <g
                        v-for="item in series"
                        :key="item.id"
                        :opacity="seriesOpacity(item.id)"
                        class="transition-opacity duration-200"
                    >
                        <path
                            :d="item.path"
                            fill="none"
                            :stroke="item.color"
                            :stroke-width="seriesStrokeWidth(item.id)"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="transition-all duration-200"
                        />
                        <circle
                            v-for="point in item.points"
                            :key="`${item.id}-${point.day}`"
                            :cx="point.x"
                            :cy="point.y"
                            :r="
                                isPointHovered(item.id, point.day)
                                    ? hoverPointRadius
                                    : pointRadius
                            "
                            :fill="item.color"
                            stroke="var(--card)"
                            stroke-width="1.5"
                            class="transition-all duration-150"
                        />
                        <circle
                            v-for="point in item.points"
                            :key="`hit-${item.id}-${point.day}`"
                            :cx="point.x"
                            :cy="point.y"
                            :r="hitRadius"
                            fill="transparent"
                            pointer-events="all"
                            class="cursor-pointer outline-none"
                            role="img"
                            tabindex="0"
                            :aria-label="`${item.name}, ${formatShortDate(point.day)}: ${formatChips(point.chips)} fichas`"
                            @mouseenter="showPoint(item, point)"
                            @mouseleave="clearHover"
                            @focus="showPoint(item, point)"
                            @blur="clearHover"
                            @keydown.escape="clearHover"
                        />
                    </g>

                    <g v-if="hoveredPoint" class="pointer-events-none">
                        <line
                            :x1="hoveredPoint.x"
                            :x2="hoveredPoint.x"
                            :y1="padding.top"
                            :y2="height - padding.bottom"
                            stroke="var(--border)"
                            stroke-dasharray="3 3"
                            stroke-opacity="0.9"
                        />
                        <circle
                            :cx="hoveredPoint.x"
                            :cy="hoveredPoint.y"
                            :r="hoverRingRadius"
                            fill="none"
                            :stroke="hoveredPoint.color"
                            stroke-width="2"
                        />
                        <g
                            :transform="`translate(${tooltipPosition.x}, ${tooltipPosition.y})`"
                        >
                            <rect
                                :width="tooltipWidth"
                                :height="tooltipHeight"
                                rx="6"
                                fill="var(--popover)"
                                stroke="var(--border)"
                            />
                            <circle
                                :cx="tooltipDotX"
                                :cy="tooltipDotY"
                                :r="tooltipDotRadius"
                                :fill="hoveredPoint.color"
                            />
                            <text
                                :x="tooltipNameX"
                                :y="tooltipNameY"
                                :font-size="tooltipNameFontSize"
                                font-weight="600"
                                class="fill-popover-foreground"
                            >
                                {{ tooltipName }}
                            </text>
                            <text
                                :x="tooltipDetailX"
                                :y="tooltipDetailY"
                                :font-size="tooltipDetailFontSize"
                                class="fill-muted-foreground"
                            >
                                {{ tooltipDetail }}
                            </text>
                        </g>
                    </g>
                </svg>
            </div>

            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="item in series"
                    :key="`legend-${item.id}`"
                    type="button"
                    class="flex items-center rounded-md border transition-all"
                    :class="[
                        props.large
                            ? 'gap-2.5 px-4 py-2 text-base'
                            : 'gap-1.5 px-2 py-1 text-xs',
                        activeId === item.id
                            ? 'border-border bg-muted/60 shadow-sm'
                            : 'border-transparent hover:border-border hover:bg-muted/40',
                    ]"
                    :style="{ opacity: seriesOpacity(item.id) < 1 && activeId !== item.id ? 0.5 : 1 }"
                    :aria-pressed="pinnedId === item.id"
                    :title="
                        pinnedId === item.id
                            ? 'Clique para mostrar todos'
                            : 'Clique para destacar este jogador'
                    "
                    @mouseenter="highlightedId = item.id"
                    @mouseleave="highlightedId = null"
                    @focus="highlightedId = item.id"
                    @blur="highlightedId = null"
                    @click="togglePin(item.id)"
                >
                    <Avatar
                        :class="`${legendAvatarClass} overflow-hidden rounded-full`"
                    >
                        <AvatarImage
                            v-if="item.avatar"
                            :src="item.avatar"
                            :alt="item.name"
                        />
                        <AvatarFallback :class="legendFallbackClass">
                            {{ getInitials(item.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <span
                        :class="`${legendDotClass} rounded-full`"
                        :style="{ backgroundColor: item.color }"
                    />
                    <span class="font-medium">{{ item.name }}</span>
                    <span class="tabular-nums text-muted-foreground">
                        {{ item.last === null ? '—' : formatChips(item.last) }}
                    </span>
                </button>
            </div>
        </div>

        <Dialog v-if="expandable" v-model:open="chartOpen">
            <DialogContent
                class="max-h-[94vh] w-[96vw] max-w-[96vw] overflow-y-auto p-4 sm:p-6 sm:max-w-[1400px]"
            >
                <DialogTitle class="sr-only">
                    Evolução das fichas por jogador
                </DialogTitle>
                <ChipTrendChart
                    :players="players"
                    :snapshots="snapshots"
                    :large="true"
                    :expandable="false"
                />
            </DialogContent>
        </Dialog>
    </div>
</template>
