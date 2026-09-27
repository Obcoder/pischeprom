<script setup>
import { onMounted, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const emailInput = ref(null);
const passwordInput = ref(null);
const passwordVisible = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

onMounted(() => emailInput.value?.focus({ preventScroll: true }));

const submit = () => {
    if (form.processing) return;

    form.transform(data => ({
        ...data,
        remember: form.remember ? 'on' : '',
    })).post(route('Ameise.login.store'), {
        onError: errors => {
            if (errors.email) emailInput.value?.focus();
            else if (errors.password) passwordInput.value?.focus();
        },
        onFinish: () => {
            form.reset('password');
            passwordVisible.value = false;
        },
    });
};
</script>

<template>
    <Head title="Вход в Ameise" />

    <main class="ameise-login" lang="ru">
        <div class="login-shell">
            <div class="login-caption" aria-hidden="true">
                <span>AMEISE / ПАНЕЛЬ УПРАВЛЕНИЯ</span>
                <span class="login-caption__mark">↗</span>
            </div>

            <section class="login-card" aria-labelledby="login-title">
                <aside class="login-brand" aria-label="Ameise — система управления">
                    <div class="login-brand__wordmark">ameise<span>.</span></div>

                    <svg class="login-brand__graphic" viewBox="0 0 240 240" fill="none" aria-hidden="true">
                        <circle cx="120" cy="120" r="100" stroke="currentColor" stroke-opacity=".25" />
                        <path d="M0 120H240M120 0V240" stroke="currentColor" stroke-opacity=".2" />
                        <path d="M44 32V44H32M196 32V44H208M44 208V196H32M196 208V196H208" stroke="currentColor" />
                        <circle cx="193" cy="50" r="17" fill="#DFFF84" />
                        <g stroke="currentColor" stroke-width="5" stroke-linecap="square" stroke-linejoin="miter">
                            <path d="M108 77L89 56V42M132 77L151 56V42" />
                            <path d="M108 111L76 91L58 103M132 111L164 91L182 103" />
                            <path d="M107 124H72L55 143M133 124H168L185 143" />
                            <path d="M108 139L80 161V185M132 139L160 161V185" />
                        </g>
                        <ellipse cx="120" cy="87" rx="21" ry="24" fill="currentColor" />
                        <circle cx="120" cy="123" r="15" fill="currentColor" />
                        <ellipse cx="120" cy="168" rx="29" ry="35" fill="currentColor" />
                        <path d="M97 163H143M96 174H144" stroke="#244BE8" stroke-width="3" />
                    </svg>

                    <div class="login-brand__footer">
                        <span>Система<br>управления</span>
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 12H20M13 5L20 12L13 19" stroke="currentColor" stroke-width="1.5" />
                        </svg>
                    </div>
                </aside>

                <div class="login-content">
                    <header class="login-heading">
                        <div class="login-eyebrow">
                            <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <rect x="3.5" y="7" width="9" height="7" stroke="currentColor" />
                                <path d="M5.5 7V4.5a2.5 2.5 0 0 1 5 0V7M8 10V11.5" stroke="currentColor" />
                            </svg>
                            Рабочее пространство
                        </div>
                        <h1 id="login-title">Вход в Ameise</h1>
                        <p>Используйте свою учётную запись.</p>
                    </header>

                    <div v-if="status" class="login-status" role="status">{{ status }}</div>

                    <form class="login-form" :aria-busy="form.processing" @submit.prevent="submit">
                        <div class="login-field">
                            <label for="email">Электронная почта</label>
                            <input
                                id="email"
                                ref="emailInput"
                                v-model="form.email"
                                name="email"
                                type="email"
                                placeholder="name@company.ru"
                                required
                                autofocus
                                autocomplete="username"
                                autocapitalize="none"
                                :spellcheck="false"
                                :aria-invalid="Boolean(form.errors.email)"
                                :aria-describedby="form.errors.email ? 'email-error' : undefined"
                            >
                            <p v-if="form.errors.email" id="email-error" class="login-error" role="alert">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="login-field">
                            <label for="password">Пароль</label>
                            <div class="login-password">
                                <input
                                    id="password"
                                    ref="passwordInput"
                                    v-model="form.password"
                                    name="password"
                                    :type="passwordVisible ? 'text' : 'password'"
                                    placeholder="Введите пароль"
                                    required
                                    autocomplete="current-password"
                                    :aria-invalid="Boolean(form.errors.password)"
                                    :aria-describedby="form.errors.password ? 'password-error' : undefined"
                                >
                                <button
                                    type="button"
                                    class="login-password__toggle"
                                    :aria-label="passwordVisible ? 'Скрыть пароль' : 'Показать пароль'"
                                    :title="passwordVisible ? 'Скрыть пароль' : 'Показать пароль'"
                                    aria-controls="password"
                                    @click="passwordVisible = !passwordVisible"
                                >
                                    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke="currentColor" stroke-width="1.5" />
                                        <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5" />
                                        <path v-if="passwordVisible" d="M4 4L20 20" stroke="currentColor" stroke-width="1.5" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.password" id="password-error" class="login-error" role="alert">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <div class="login-options">
                            <label class="login-remember">
                                <input v-model="form.remember" type="checkbox" name="remember">
                                <span>Запомнить меня</span>
                            </label>
                            <Link v-if="canResetPassword" :href="route('password.request')" class="login-link">
                                Забыли пароль?
                            </Link>
                        </div>

                        <button class="login-submit" type="submit" :disabled="form.processing">
                            <span>{{ form.processing ? 'Входим…' : 'Войти' }}</span>
                            <span v-if="form.processing" class="login-spinner" aria-hidden="true" />
                            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 12H20M13 5L20 12L13 19" stroke="currentColor" stroke-width="1.5" />
                            </svg>
                        </button>
                    </form>
                </div>
            </section>

            <footer class="login-footer">
                <Link href="/" class="login-back">
                    <span aria-hidden="true">←</span> На сайт
                </Link>
                <span>ПИЩЕПРОМ-СЕРВЕР</span>
            </footer>
        </div>
    </main>
</template>

<style scoped>
.ameise-login {
    --login-ink: #202522;
    --login-muted: #646b67;
    --login-blue: #244be8;
    display: grid;
    min-height: 100vh;
    min-height: 100svh;
    place-items: center;
    padding: 40px 24px;
    background-color: #f3f3ed;
    background-image: linear-gradient(#20252206 1px, transparent 1px), linear-gradient(90deg, #20252206 1px, transparent 1px);
    background-size: 32px 32px;
    color: var(--login-ink);
    font-family: Arial, Helvetica, sans-serif;
    -webkit-font-smoothing: antialiased;
}

.login-shell { width: 100%; max-width: 740px; }

.login-caption,
.login-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    font-family: 'JetBrains Mono', 'SFMono-Regular', Consolas, monospace;
    font-size: 10px;
    font-weight: 500;
    letter-spacing: .08em;
}

.login-caption { margin-bottom: 12px; }
.login-caption__mark { font-size: 21px; line-height: 1; }

.login-card {
    display: grid;
    grid-template-columns: 268px minmax(0, 1fr);
    border: 1px solid var(--login-ink);
    background: #fff;
    box-shadow: 6px 6px 0 var(--login-ink);
}

.login-brand {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    gap: 28px;
    overflow: hidden;
    padding: 29px 28px;
    border-right: 1px solid var(--login-ink);
    background: var(--login-blue);
    color: #fff;
}

.login-brand__wordmark { font-size: 46px; font-weight: 700; letter-spacing: -.065em; line-height: 1; }
.login-brand__wordmark span { color: #dfff84; }
.login-brand__graphic { width: 100%; max-width: 210px; align-self: center; }
.login-brand__footer { display: flex; align-items: flex-end; justify-content: space-between; font-size: 12px; line-height: 1.5; }
.login-content { align-self: center; min-width: 0; padding: 38px; }
.login-heading { margin-bottom: 28px; }

.login-eyebrow {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 14px;
    color: var(--login-muted);
    font-size: 10px;
    letter-spacing: .08em;
    text-transform: uppercase;
}

.login-heading h1 { margin: 0; font-size: 29px; font-weight: 600; letter-spacing: -.045em; line-height: 1.15; }
.login-heading p { margin-top: 9px; color: var(--login-muted); font-size: 13px; line-height: 1.5; }
.login-form { display: grid; gap: 18px; }
.login-field > label { display: block; margin-bottom: 7px; font-size: 12px; font-weight: 600; line-height: 1.4; }

.login-field input {
    display: block;
    width: 100%;
    height: 44px;
    padding: 0 12px;
    border: 1px solid #bbc0b9;
    border-radius: 0;
    background: #fcfcf9;
    color: var(--login-ink);
    font: inherit;
    font-size: 14px;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}

.login-field input::placeholder { color: #777e77; opacity: 1; }
.login-field input:hover { border-color: var(--login-ink); }
.login-field input:focus { border-color: var(--login-blue); box-shadow: 0 0 0 3px #244be81a; }
.login-field input[aria-invalid='true'] { border-color: #b42318; }
.login-password { position: relative; }
.login-password input { padding-right: 46px; }

.login-password__toggle {
    position: absolute;
    inset: 1px 1px 1px auto;
    display: grid;
    width: 42px;
    place-items: center;
    color: var(--login-muted);
}

.login-password__toggle:hover { color: var(--login-blue); }

.login-options {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 16px;
    font-size: 12px;
    line-height: 1.5;
}

.login-remember { display: inline-flex; align-items: center; gap: 8px; min-height: 28px; cursor: pointer; }
.login-remember input { width: 15px; height: 15px; accent-color: var(--login-blue); cursor: pointer; }
.login-link { padding: 5px 0; color: var(--login-blue); text-decoration: underline; text-decoration-color: #244be84d; text-underline-offset: 3px; }
.login-link:hover { text-decoration-color: currentColor; }

.login-submit {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-height: 46px;
    padding: 12px 16px;
    border: 1px solid var(--login-ink);
    background: var(--login-ink);
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.5;
    transition: background .15s, border-color .15s;
}

.login-submit:hover:not(:disabled) { border-color: var(--login-blue); background: var(--login-blue); }
.login-submit:disabled { cursor: wait; opacity: .65; }

.login-password__toggle:focus-visible,
.login-remember input:focus-visible,
.login-link:focus-visible,
.login-submit:focus-visible,
.login-back:focus-visible { outline: 2px solid var(--login-blue); outline-offset: 4px; }

.login-error { margin-top: 6px; color: #b42318; font-size: 12px; line-height: 1.5; overflow-wrap: anywhere; }

.login-status {
    margin-bottom: 20px;
    padding: 10px 12px;
    border-left: 2px solid #32754b;
    background: #edf7ee;
    color: #215c37;
    font-size: 13px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.login-spinner {
    width: 18px;
    height: 18px;
    border: 2px solid #ffffff4d;
    border-top-color: #fff;
    border-radius: 50%;
    animation: login-spin .7s linear infinite;
}

.login-footer { margin-top: 22px; color: var(--login-muted); font-size: 9px; }
.login-back { display: inline-flex; align-items: center; gap: 8px; min-height: 28px; color: var(--login-ink); font-family: Arial, Helvetica, sans-serif; font-size: 12px; letter-spacing: 0; }
.login-back:hover { color: var(--login-blue); }

@keyframes login-spin {
    to { transform: rotate(360deg); }
}

@media (max-width: 640px) {
    .ameise-login { padding: 28px 20px; }
    .login-shell { max-width: 420px; }
    .login-card { grid-template-columns: minmax(0, 1fr); box-shadow: 4px 4px 0 var(--login-ink); }
    .login-brand { flex-direction: row; align-items: center; gap: 16px; padding: 18px 26px; border-right: 0; border-bottom: 1px solid var(--login-ink); }
    .login-brand__wordmark { font-size: 36px; }
    .login-brand__graphic { width: 64px; height: 64px; }
    .login-brand__footer { display: none; }
    .login-content { padding: 28px 26px; }
    .login-heading { margin-bottom: 24px; }
    .login-heading h1 { font-size: 27px; }
    .login-field input { font-size: 16px; }
}

@media (prefers-reduced-motion: reduce) {
    .login-field input, .login-submit { transition: none; }
    .login-spinner { animation: none; }
}
</style>
