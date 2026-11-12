<script setup lang="ts">
import { Loader2, RotateCcw, ZoomIn, ZoomOut } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = defineProps<{
    file: File | null;
}>();

const emit = defineEmits<{
    cropped: [file: File];
    close: [];
}>();

const MAX_ZOOM = 4;
const OUTPUT_SIZE = 512;

const open = ref(false);
const viewport = ref<HTMLElement | null>(null);
const viewportSize = ref(0);
const zoom = ref(1);
const offsetX = ref(0);
const offsetY = ref(0);
const image = ref<HTMLImageElement | null>(null);
const imageUrl = ref<string | null>(null);
const loaded = ref(false);
const saving = ref(false);
const error = ref<string | null>(null);

let observer: ResizeObserver | null = null;

const clamp = (value: number, min: number, max: number): number =>
    Math.min(max, Math.max(min, value));

const naturalWidth = computed(() => image.value?.naturalWidth ?? 0);
const naturalHeight = computed(() => image.value?.naturalHeight ?? 0);
const viewportSide = computed(() => viewportSize.value || 1);

const baseScale = computed(() => {
    if (!naturalWidth.value || !naturalHeight.value) {
        return 1;
    }

    return Math.max(
        viewportSide.value / naturalWidth.value,
        viewportSide.value / naturalHeight.value,
    );
});

const renderedWidth = computed(
    () => naturalWidth.value * baseScale.value * zoom.value,
);
const renderedHeight = computed(
    () => naturalHeight.value * baseScale.value * zoom.value,
);

const maxOffsetX = computed(() =>
    Math.max(0, (renderedWidth.value - viewportSide.value) / 2),
);
const maxOffsetY = computed(() =>
    Math.max(0, (renderedHeight.value - viewportSide.value) / 2),
);

const clampOffsets = (): void => {
    offsetX.value = clamp(offsetX.value, -maxOffsetX.value, maxOffsetX.value);
    offsetY.value = clamp(offsetY.value, -maxOffsetY.value, maxOffsetY.value);
};

const imageStyle = computed(() => ({
    width: `${renderedWidth.value}px`,
    height: `${renderedHeight.value}px`,
    transform: `translate(calc(-50% + ${offsetX.value}px), calc(-50% + ${offsetY.value}px))`,
}));

const canReset = computed(
    () =>
        Math.abs(zoom.value - 1) > 0.001 ||
        Math.abs(offsetX.value) > 0.5 ||
        Math.abs(offsetY.value) > 0.5,
);

const releaseImage = (): void => {
    if (imageUrl.value) {
        URL.revokeObjectURL(imageUrl.value);

        imageUrl.value = null;
    }

    image.value = null;
    loaded.value = false;
};

const reset = (): void => {
    zoom.value = 1;
    offsetX.value = 0;
    offsetY.value = 0;
};

const loadFile = (file: File | null): void => {
    releaseImage();
    reset();
    error.value = null;

    if (!file) {
        return;
    }

    const url = URL.createObjectURL(file);

    imageUrl.value = url;

    const element = new Image();

    element.decoding = 'async';
    element.onload = () => {
        if (imageUrl.value !== url) {
            return;
        }

        image.value = element;
        loaded.value = true;
        nextTick(measureViewport);
    };
    element.onerror = () => {
        if (imageUrl.value !== url) {
            return;
        }

        error.value = 'Não foi possível abrir esta imagem.';
    };
    element.src = url;
};

const measureViewport = (): void => {
    const element = viewport.value;

    if (!element) {
        return;
    }

    const size = Math.min(element.clientWidth, element.clientHeight);

    if (size > 0) {
        viewportSize.value = size;
    }
};

const observeViewport = (): void => {
    observer?.disconnect();
    observer = null;

    const element = viewport.value;

    if (!element) {
        return;
    }

    observer = new ResizeObserver(measureViewport);
    observer.observe(element);
    measureViewport();
};

const zoomTo = (
    value: number,
    focalX = viewportSide.value / 2,
    focalY = viewportSide.value / 2,
): void => {
    const next = clamp(value, 1, MAX_ZOOM);
    const ratio = next / zoom.value;

    if (!Number.isFinite(ratio) || ratio === 1) {
        return;
    }

    const fx = focalX - viewportSide.value / 2;
    const fy = focalY - viewportSide.value / 2;

    offsetX.value = fx + (offsetX.value - fx) * ratio;
    offsetY.value = fy + (offsetY.value - fy) * ratio;
    zoom.value = next;
    clampOffsets();
};

const onZoomInput = (event: Event): void => {
    zoomTo(Number((event.target as HTMLInputElement).value));
};

const onWheel = (event: WheelEvent): void => {
    if (!loaded.value) {
        return;
    }

    const rect = viewport.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    const factor = Math.exp(-event.deltaY * 0.0015);

    zoomTo(
        zoom.value * factor,
        event.clientX - rect.left,
        event.clientY - rect.top,
    );
};

let dragging = false;
let startX = 0;
let startY = 0;
let startOffsetX = 0;
let startOffsetY = 0;

const onPointerDown = (event: PointerEvent): void => {
    if (!loaded.value) {
        return;
    }

    dragging = true;
    startX = event.clientX;
    startY = event.clientY;
    startOffsetX = offsetX.value;
    startOffsetY = offsetY.value;

    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
};

const onPointerMove = (event: PointerEvent): void => {
    if (!dragging) {
        return;
    }

    offsetX.value = clamp(
        startOffsetX + event.clientX - startX,
        -maxOffsetX.value,
        maxOffsetX.value,
    );
    offsetY.value = clamp(
        startOffsetY + event.clientY - startY,
        -maxOffsetY.value,
        maxOffsetY.value,
    );
};

const onPointerUp = (event: PointerEvent): void => {
    dragging = false;

    const target = event.currentTarget as HTMLElement;

    if (target.hasPointerCapture(event.pointerId)) {
        target.releasePointerCapture(event.pointerId);
    }
};

const stopDragging = (): void => {
    dragging = false;
};

const newFile = (blob: Blob): File => {
    const originalName = props.file?.name.replace(/\.[^.]+$/, '') || 'avatar';
    const extension =
        blob.type === 'image/webp'
            ? 'webp'
            : blob.type === 'image/jpeg'
              ? 'jpg'
              : 'png';

    return new File([blob], `${originalName}.${extension}`, {
        type: blob.type,
    });
};

const toBlob = (
    canvas: HTMLCanvasElement,
    type: string,
): Promise<Blob | null> =>
    new Promise((resolve) => canvas.toBlob(resolve, type, 0.9));

const confirm = async (): Promise<void> => {
    const source = image.value;

    if (!source || !loaded.value || !viewportSize.value) {
        return;
    }

    saving.value = true;
    error.value = null;

    try {
        const scale = baseScale.value * zoom.value;
        const renderedLeft =
            viewportSize.value / 2 - renderedWidth.value / 2 + offsetX.value;
        const renderedTop =
            viewportSize.value / 2 - renderedHeight.value / 2 + offsetY.value;

        const sourceSize = viewportSize.value / scale;
        const sourceX = -renderedLeft / scale;
        const sourceY = -renderedTop / scale;
        const sx = clamp(
            sourceX,
            0,
            Math.max(0, naturalWidth.value - sourceSize),
        );
        const sy = clamp(
            sourceY,
            0,
            Math.max(0, naturalHeight.value - sourceSize),
        );
        const cropSize = Math.min(
            sourceSize,
            naturalWidth.value - sx,
            naturalHeight.value - sy,
        );

        if (cropSize <= 0) {
            throw new Error('Não foi possível recortar a imagem.');
        }

        const size = Math.max(1, Math.min(OUTPUT_SIZE, Math.floor(cropSize)));
        const canvas = document.createElement('canvas');

        canvas.width = size;
        canvas.height = size;

        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Seu navegador não suporta recorte de imagem.');
        }

        context.imageSmoothingQuality = 'high';
        context.drawImage(source, sx, sy, cropSize, cropSize, 0, 0, size, size);

        const blob =
            (await toBlob(canvas, 'image/webp')) ??
            (await toBlob(canvas, 'image/jpeg'));

        if (!blob) {
            throw new Error('Não foi possível gerar a imagem recortada.');
        }

        open.value = false;
        emit('cropped', newFile(blob));
    } catch (exception) {
        error.value =
            exception instanceof Error
                ? exception.message
                : 'Não foi possível recortar a imagem.';
    } finally {
        saving.value = false;
    }
};

const close = (): void => {
    if (!open.value) {
        return;
    }

    open.value = false;
    emit('close');
};

const onDialogOpenChange = (value: boolean): void => {
    if (!value) {
        close();
    }
};

watch(
    () => props.file,
    (file) => {
        loadFile(file);
        open.value = file !== null;
    },
    { immediate: true },
);

watch(
    open,
    async (value) => {
        if (!value) {
            observer?.disconnect();
            observer = null;
            reset();

            return;
        }

        await nextTick();
        requestAnimationFrame(observeViewport);
    },
    { immediate: true },
);

watch([renderedWidth, renderedHeight, viewportSize], clampOffsets);

onBeforeUnmount(() => {
    observer?.disconnect();
    observer = null;
    releaseImage();
});
</script>

<template>
    <Dialog :open="open" @update:open="onDialogOpenChange">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Ajustar foto</DialogTitle>
                <DialogDescription>
                    Arraste para reposicionar e use o zoom para enquadrar o
                    rosto. A área circular é como a foto vai aparecer.
                </DialogDescription>
            </DialogHeader>

            <div class="flex justify-center">
                <div
                    ref="viewport"
                    class="relative aspect-square w-full max-w-72 touch-none overflow-hidden rounded-lg border bg-muted select-none"
                    :class="
                        loaded
                            ? 'cursor-grab active:cursor-grabbing'
                            : 'cursor-default'
                    "
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="onPointerUp"
                    @lostpointercapture="stopDragging"
                    @wheel.prevent="onWheel"
                >
                    <img
                        v-if="imageUrl && loaded && viewportSize"
                        :src="imageUrl"
                        :style="imageStyle"
                        alt=""
                        draggable="false"
                        class="absolute top-1/2 left-1/2 max-w-none"
                        @dragstart.prevent
                    />

                    <template v-if="loaded && viewportSize">
                        <div
                            class="pointer-events-none absolute inset-0 rounded-full shadow-[0_0_0_9999px_rgba(0,0,0,0.6)] ring-1 ring-white/70"
                        />
                    </template>

                    <div
                        v-if="!loaded && !error"
                        class="absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
                    >
                        <Loader2 class="mr-2 size-4 animate-spin" />
                        Carregando…
                    </div>

                    <div
                        v-if="error"
                        class="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-destructive"
                    >
                        {{ error }}
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    aria-label="Diminuir zoom"
                    :disabled="!loaded || zoom <= 1"
                    @click="zoomTo(zoom - 0.25)"
                >
                    <ZoomOut class="size-4" />
                </Button>

                <input
                    type="range"
                    min="1"
                    :max="MAX_ZOOM"
                    step="0.01"
                    :value="zoom"
                    aria-label="Zoom da foto"
                    class="h-1.5 w-full cursor-pointer accent-primary disabled:cursor-not-allowed"
                    :disabled="!loaded"
                    @input="onZoomInput"
                />

                <Button
                    type="button"
                    variant="outline"
                    size="icon-sm"
                    aria-label="Aumentar zoom"
                    :disabled="!loaded || zoom >= MAX_ZOOM"
                    @click="zoomTo(zoom + 0.25)"
                >
                    <ZoomIn class="size-4" />
                </Button>
            </div>

            <div class="flex items-center justify-between gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="!loaded || !canReset"
                    @click="reset"
                >
                    <RotateCcw class="size-3.5" />
                    Centralizar
                </Button>
                <span class="text-xs text-muted-foreground tabular-nums">
                    {{ Math.round(zoom * 100) }}%
                </span>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="saving"
                    @click="close"
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    :disabled="!loaded || saving || !!error"
                    @click="confirm"
                >
                    <Loader2 v-if="saving" class="size-4 animate-spin" />
                    Aplicar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
