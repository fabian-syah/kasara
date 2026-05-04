<script setup>
import { ref, onMounted, nextTick, watch } from "vue";
import { Lock, X, AlertCircle, CheckCircle } from "lucide-vue-next";
import api, { auth as authApi } from "../../api/axios";
import { useToast } from "../../composables/useToast";
import { useAuthStore } from "../../store/auth";

const authStore = useAuthStore();

const props = defineProps({
    show: Boolean,
    title: { type: String, default: "Verifikasi PIN Transaksi" },
    description: { type: String, default: "Masukkan 4 digit PIN transaksi Anda untuk melanjutkan." },
    mode: { type: String, default: "verify" }, // 'verify', 'set', 'setup_initial'
    user: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: "" }
});

const emit = defineEmits(["close", "success", "verified", "error"]);

const pin = ref(["", "", "", ""]);
const inputs = ref([]);
const localLoading = ref(false);

function handleInput(index, event) {
    const val = event.target.value;
    if (val && !/^\d+$/.test(val)) {
        pin.value[index] = "";
        return;
    }

    if (val.length > 1) {
        pin.value[index] = val.slice(-1);
    }

    if (val && index < 3) {
        inputs.value[index + 1].focus();
    }

    if (pin.value.every(v => v !== "")) {
        handleSubmit();
    }
}

function handleKeydown(index, event) {
    if (event.key === "Backspace" && !pin.value[index] && index > 0) {
        inputs.value[index - 1].focus();
    }
}

async function handleSubmit() {
    const pinStr = pin.value.join("");
    if (pinStr.length < 4) return;

    emit("success", pinStr);
    emit("verified", pinStr);
    resetPin();
}

const toast = useToast();

async function handleRequestReset() {
    if (!confirm("Kirim permintaan reset PIN ke Admin?")) return;

    localLoading.value = true;
    try {
        await authApi.requestResetPin();
        toast.success("Permintaan reset PIN telah dikirim.");

        // Update store locally so UI reacts immediately
        if (authStore.user) {
            authStore.user.pin_reset_requested_at = new Date().toISOString();
            // Sync with localStorage
            localStorage.setItem('user', JSON.stringify(authStore.user));
        }

        close();
    } catch (err) {
        toast.error("Gagal mengirim permintaan reset.");
        console.error(err);
    } finally {
        localLoading.value = false;
    }
}

watch(() => props.error, (newVal) => {
    if (newVal) {
        resetPin();
    }
});

function resetPin() {
    pin.value = ["", "", "", ""];
    nextTick(() => {
        if (inputs.value[0]) inputs.value[0].focus();
    });
}

function close() {
    resetPin();
    emit("close");
}

onMounted(() => {
    if (props.show) {
        nextTick(() => {
            if (inputs.value[0]) inputs.value[0].focus();
        });
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" v-bind="$attrs">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/80 backdrop-blur-md" @click="close"></div>

            <!-- Modal Content -->
            <div
                class="relative w-full max-w-sm bg-white dark:bg-surface-900 border border-gray-200 dark:border-white/10 rounded-[2.5rem] shadow-2xl overflow-hidden animate-in fade-in zoom-in duration-300">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-emerald-500 to-emerald-800"></div>

                <div class="p-8 text-center">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-500/10 text-emerald-500 mb-6 font-black font-mono">
                        <Lock :size="32" stroke-width="2.5" />
                    </div>

                    <h3 class="text-2xl font-black text-text-primary mb-2">{{ title }}</h3>
                    <p v-if="user" class="text-sm font-bold text-emerald-500 mb-2">
                        Akun: {{ user.name || user.full_name }}
                    </p>
                    <p class="text-text-secondary text-sm mb-8 leading-relaxed px-4">
                        {{ description }}
                    </p>

                    <div class="flex justify-center gap-3 mb-4">
                        <input v-for="(digit, idx) in 4" :key="idx" :ref="el => inputs[idx] = el" v-model="pin[idx]"
                            type="password" inputmode="numeric" maxlength="1" autocomplete="new-password"
                            class="w-14 h-16 bg-surface-50 dark:bg-white/5 border border-surface-200 dark:border-white/10 rounded-2xl text-center text-3xl font-black text-text-primary focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition-all placeholder:text-text-secondary/30"
                            @input="handleInput(idx, $event)" @keydown="handleKeydown(idx, $event)" />
                    </div>

                    <div v-if="mode === 'verify'" class="mb-6">
                        <button v-if="!user?.pin_reset_requested_at && !authStore.user?.pin_reset_requested_at" @click="handleRequestReset"
                            class="text-xs text-primary-400 hover:text-primary-300 font-medium transition-colors underline underline-offset-4">
                            Lupa PIN? Hubungi Audit Cabang
                        </button>
                        <div v-else
                            class="text-[10px] text-amber-500 font-bold uppercase tracking-wider bg-amber-500/10 py-2 px-3 rounded-xl border border-amber-500/20">
                            ⏳ Permintaan reset sedang diproses
                        </div>
                    </div>

                    <div v-if="error"
                        class="flex items-center justify-center gap-2 text-rose-500 text-sm font-bold mb-6 animate-pulse">
                        <AlertCircle :size="16" />
                        {{ error }}
                    </div>

                    <div v-if="loading || localLoading" class="flex items-center justify-center gap-3 py-4">
                        <div class="w-2 h-2 bg-primary-500 rounded-full animate-bounce [animation-delay:-0.3s]"></div>
                        <div class="w-2 h-2 bg-primary-500 rounded-full animate-bounce [animation-delay:-0.15s]"></div>
                        <div class="w-2 h-2 bg-primary-500 rounded-full animate-bounce"></div>
                    </div>

                    <button @click="close"
                        class="text-text-secondary/60 hover:text-text-primary text-sm font-bold transition-colors">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>


<style scoped>
/* Chrome, Safari, Edge, Opera */
input::-webkit-outer-spin-button,
input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    appearance: none;
    margin: 0;
}

/* Firefox */
input[type=number] {
    -moz-appearance: textfield;
    appearance: textfield;
}
</style>
