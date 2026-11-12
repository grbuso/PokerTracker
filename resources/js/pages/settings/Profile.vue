<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DiscordLinkController from '@/actions/App/Http/Controllers/Settings/DiscordLinkController';
import AvatarCropper from '@/components/AvatarCropper.vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useInitials } from '@/composables/useInitials';
import { edit } from '@/routes/profile';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Configurações de perfil',
                href: edit(),
            },
        ],
    },
});

const props = defineProps<{
    canDeleteAccount: boolean;
    discordLinkToken: string | null;
    discordLinkExpiresAt: string | null;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const { getInitials } = useInitials();

const discordLinkExpiry = computed(() => {
    if (!props.discordLinkExpiresAt) {
        return null;
    }

    return new Date(props.discordLinkExpiresAt).toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit',
    });
});

const preview = ref<string | null>(null);
const avatarFileName = ref<string | null>(null);
const avatarInput = ref<HTMLInputElement | null>(null);
const pendingAvatar = ref<File | null>(null);
const confirmedAvatar = ref<File | null>(null);

const setInputFile = (file: File | null): void => {
    const input = avatarInput.value;

    if (!input) {
        return;
    }

    const transfer = new DataTransfer();

    if (file) {
        transfer.items.add(file);
    }

    input.files = transfer.files;
};

const onAvatarChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    input.value = '';

    if (!file) {
        return;
    }

    pendingAvatar.value = file;
};

const onAvatarCropped = (file: File) => {
    confirmedAvatar.value = file;
    setInputFile(file);

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = URL.createObjectURL(file);
    avatarFileName.value = file.name;
    pendingAvatar.value = null;
};

const onAvatarCropClose = () => {
    setInputFile(confirmedAvatar.value);
    pendingAvatar.value = null;
};

const onProfileSuccess = () => {
    confirmedAvatar.value = null;
    setInputFile(null);
    avatarFileName.value = null;

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
        preview.value = null;
    }
};

onBeforeUnmount(() => {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
});
</script>

<template>
    <Head title="Configurações de perfil" />

    <h1 class="sr-only">Configurações de perfil</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Perfil"
            description="Atualize seu nome e endereço de email"
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            :on-success="onProfileSuccess"
            v-slot="{ errors, processing }"
        >
            <div class="flex items-center gap-4">
                <Avatar class="size-20 overflow-hidden rounded-full">
                    <AvatarImage
                        v-if="preview || user.avatar"
                        :src="preview ?? user.avatar!"
                        :alt="user.name"
                    />
                    <AvatarFallback class="text-lg">
                        {{ getInitials(user.name) }}
                    </AvatarFallback>
                </Avatar>

                <div class="grid gap-2">
                    <Label for="avatar">Foto de perfil</Label>
                    <label
                        for="avatar"
                        class="flex cursor-pointer items-center gap-3 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-colors focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 hover:bg-muted/50"
                    >
                        <span
                            class="inline-flex h-7 shrink-0 items-center rounded-md bg-secondary px-3 text-sm font-medium text-secondary-foreground"
                        >
                            Escolher imagem
                        </span>
                        <span
                            class="truncate"
                            :class="
                                avatarFileName
                                    ? 'text-foreground'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ avatarFileName ?? 'Nenhum arquivo selecionado' }}
                        </span>
                        <input
                            id="avatar"
                            ref="avatarInput"
                            name="avatar"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="onAvatarChange"
                        />
                    </label>
                    <InputError :message="errors.avatar" />
                    <p class="text-xs text-muted-foreground">
                        JPG, PNG ou WebP, até 10 MB.
                    </p>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="name">Nome</Label>
                <Input
                    id="name"
                    class="mt-1 block w-full"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Nome completo"
                />
                <InputError class="mt-2" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Endereço de email</Label>
                <Input
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="Endereço de email"
                />
                <InputError class="mt-2" :message="errors.email" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-profile-button"
                    >Salvar</Button
                >
            </div>
        </Form>

        <Heading
            variant="small"
            title="Discord"
            description="Vincule sua conta para enviar as fichas do dia pelo bot"
        />

        <div v-if="user.discord_id" class="rounded-lg border p-4 text-sm">
            <p class="font-medium">Conta vinculada</p>
            <p class="text-muted-foreground">
                Seu Discord já está ligado a esta conta. Use
                <code class="rounded bg-muted px-1 py-0.5">/fichas</code>
                no canal de fichas.
            </p>
        </div>

        <div v-else class="space-y-3">
            <p class="text-sm text-muted-foreground">
                Gere um código temporário e envie
                <code class="rounded bg-muted px-1 py-0.5"
                    >/vincular CÓDIGO</code
                >
                no canal de fichas do Discord.
            </p>

            <Form
                v-bind="DiscordLinkController.store.form()"
                v-slot="{ processing }"
            >
                <Button :disabled="processing">Gerar código</Button>
            </Form>

            <div
                v-if="discordLinkToken"
                class="rounded-lg border bg-muted/40 p-4"
            >
                <p
                    class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Seu código
                </p>
                <p class="font-mono text-lg font-semibold tracking-[0.2em]">
                    {{ discordLinkToken }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Use
                    <code class="rounded bg-muted px-1 py-0.5"
                        >/vincular {{ discordLinkToken }}</code
                    >
                    <template v-if="discordLinkExpiry">
                        até {{ discordLinkExpiry }}.
                    </template>
                </p>
            </div>
        </div>
    </div>

    <DeleteUser v-if="canDeleteAccount" />

    <AvatarCropper
        :file="pendingAvatar"
        @cropped="onAvatarCropped"
        @close="onAvatarCropClose"
    />
</template>
