<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
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

defineProps<{ canDeleteAccount: boolean }>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const { getInitials } = useInitials();

const preview = ref<string | null>(null);
const avatarFileName = ref<string | null>(null);

const onAvatarChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = file ? URL.createObjectURL(file) : null;
    avatarFileName.value = file?.name ?? null;
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
    </div>

    <DeleteUser v-if="canDeleteAccount" />
</template>
