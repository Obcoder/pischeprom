<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

defineProps({
    canResetPassword: Boolean,
    status: String,
});

const page = usePage();
const canRegister = computed(() => Boolean(page.props.canRegister));
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
    })).post(route('login'), {
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
    <Head title="Вход в личный кабинет" />

    <main class="customer-login" lang="ru">
        <div class="customer-shell">
            <Link href="/" class="customer-brand" aria-label="Пищепром-сервер — на главную">
                <svg class="customer-brand__mark" width="36" height="36" viewBox="0 0 36 36" fill="none" aria-hidden="true">
                    <rect x=".75" y=".75" width="34.5" height="34.5" rx="10" stroke="currentColor" stroke-width="1.5" />
                    <path d="M18 27V9M18 15C12 15 10 12 10 9C15 9 18 11 18 15ZM18 21C12 21 10 18 10 15C15 15 18 17 18 21ZM18 18C24 18 26 15 26 12C21 12 18 14 18 18ZM18 24C24 24 26 21 26 18C21 18 18 20 18 24Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round" />
                </svg>
                <span>ПИЩЕПРОМ<span class="customer-brand__divider"> / </span>СЕРВЕР</span>
            </Link>

            <section class="customer-card" aria-labelledby="login-title">
                <header class="customer-heading">
                    <div class="customer-eyebrow"><span aria-hidden="true" /> Для клиентов и заказчиков</div>
                    <h1 id="login-title">Вход в личный<br>кабинет</h1>
                    <p>Рады видеть вас снова.</p>
                    <svg class="customer-heading__graphic" width="126" height="142" viewBox="0 0 126 142" fill="none" aria-hidden="true">
                        <circle cx="104" cy="62" r="60" stroke="currentColor" stroke-opacity=".12" />
                        <circle cx="104" cy="62" r="43" stroke="currentColor" stroke-opacity=".12" />
                        <g stroke="currentColor" stroke-width="1.5" stroke-linejoin="round">
                            <path d="M78 129V38M78 57C60 57 53 47 53 35C68 35 78 44 78 57ZM78 79C60 79 53 69 53 57C68 57 78 66 78 79ZM78 101C60 101 53 91 53 79C68 79 78 88 78 101ZM78 68C96 68 103 58 103 46C88 46 78 55 78 68ZM78 90C96 90 103 80 103 68C88 68 78 77 78 90ZM78 112C96 112 103 102 103 90C88 90 78 99 78 112Z" />
                            <path d="M78 39C67 30 68 20 78 10C88 20 89 30 78 39Z" fill="currentColor" fill-opacity=".06" />
                        </g>
                    </svg>
                </header>

                <div class="customer-content">
                    <div v-if="status" class="customer-status" role="status">{{ status }}</div>

                    <form class="customer-form" :aria-busy="form.processing" @submit.prevent="submit">
                        <div class="customer-field">
                            <label for="email">Электронная почта</label>
                            <input
                                id="email"
                                ref="emailInput"
                                v-model="form.email"
                                name="email"
                                type="email"
                                placeholder="you@example.ru"
                                required
                                autofocus
                                autocomplete="username"
                                autocapitalize="none"
                                :spellcheck="false"
                                :aria-invalid="Boolean(form.errors.email)"
                                :aria-describedby="form.errors.email ? 'email-error' : undefined"
                            >
                            <p v-if="form.errors.email" id="email-error" class="customer-error" role="alert">
                                {{ form.errors.email }}
                            </p>
                        </div>

                        <div class="customer-field">
                            <div class="customer-field__heading">
                                <label for="password">Пароль</label>
                                <Link v-if="canResetPassword" :href="route('password.request')" class="customer-link customer-recovery">
                                    Забыли пароль?
                                </Link>
                            </div>
                            <div class="customer-password">
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
                                    class="customer-password__toggle"
                                    :aria-label="passwordVisible ? 'Скрыть пароль' : 'Показать пароль'"
                                    :aria-pressed="passwordVisible"
                                    :title="passwordVisible ? 'Скрыть пароль' : 'Показать пароль'"
                                    aria-controls="password"
                                    @click="passwordVisible = !passwordVisible"
                                >
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z" stroke="currentColor" stroke-width="1.5" />
                                        <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.5" />
                                        <path v-if="passwordVisible" d="M4 4L20 20" stroke="currentColor" stroke-width="1.5" />
                                    </svg>
                                </button>
                            </div>
                            <p v-if="form.errors.password" id="password-error" class="customer-error" role="alert">
                                {{ form.errors.password }}
                            </p>
                        </div>

                        <label class="customer-remember">
                            <input v-model="form.remember" type="checkbox" name="remember">
                            <span>Запомнить меня</span>
                        </label>

                        <button class="customer-submit" type="submit" :disabled="form.processing">
                            <span>{{ form.processing ? 'Входим…' : 'Войти в кабинет' }}</span>
                            <span v-if="form.processing" class="customer-spinner" aria-hidden="true" />
                            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 12H20M13 5L20 12L13 19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </form>

                    <p v-if="canRegister" class="customer-register">
                        Ещё нет аккаунта?
                        <Link :href="route('register')" class="customer-link">Зарегистрироваться</Link>
                    </p>
                </div>
            </section>

            <footer class="customer-footer">
                <Link href="/" class="customer-back"><span aria-hidden="true">←</span> Вернуться на сайт</Link>
                <span>Пищепром-сервер</span>
            </footer>
        </div>
    </main>
</template>

<style scoped>
.customer-login {
    --customer-ink: #302520;
    --customer-muted: #796b62;
    --customer-wine: #800000;
    --customer-line: #e7ddd4;
    display: grid;
    min-height: 100vh;
    min-height: 100svh;
    place-items: center;
    padding: 32px 20px;
    background-color: #f7f2eb;
    background-image: radial-gradient(ellipse at 16% 10%, #fffaf5 0, transparent 52%), radial-gradient(ellipse at 90% 95%, #eadbcb70 0, transparent 45%);
    color: var(--customer-ink);
    font-family: Arial, Helvetica, sans-serif;
    -webkit-font-smoothing: antialiased;
}

.customer-login *, .customer-login *::before, .customer-login *::after { box-sizing: border-box; }
.customer-shell { width: 100%; max-width: 440px; }
.customer-brand { display: flex; align-items: center; justify-content: center; gap: 11px; margin-bottom: 24px; color: var(--customer-wine); font-size: 12px; font-weight: 700; letter-spacing: .095em; text-decoration: none; }
.customer-brand__mark { flex-shrink: 0; }
.customer-brand__divider { font-weight: 400; opacity: .5; }
.customer-card { overflow: hidden; border: 1px solid var(--customer-line); border-radius: 18px; background: #fff; box-shadow: 0 18px 55px #62442d0b, 0 2px 5px #62442d04; }
.customer-heading { position: relative; overflow: hidden; padding: 27px 30px 24px; border-bottom: 1px solid var(--customer-line); background: #fcf7f0; }
.customer-eyebrow { position: relative; z-index: 1; display: flex; align-items: center; gap: 7px; margin-bottom: 15px; color: var(--customer-wine); font-size: 10px; font-weight: 600; letter-spacing: .055em; text-transform: uppercase; }
.customer-eyebrow > span { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.customer-heading h1 { position: relative; z-index: 1; margin: 0; font-size: 29px; font-weight: 600; letter-spacing: -.035em; line-height: 1.16; }
.customer-heading p { position: relative; z-index: 1; margin: 11px 0 0; color: var(--customer-muted); font-size: 13px; line-height: 1.5; }
.customer-heading__graphic { position: absolute; right: 0; bottom: 6px; color: #a57d59; pointer-events: none; }
.customer-content { padding: 26px 30px 25px; }
.customer-form { display: grid; gap: 19px; }
.customer-field { min-width: 0; }
.customer-field label { display: block; color: var(--customer-ink); font-size: 12px; font-weight: 600; line-height: 1.5; }
.customer-field__heading { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.customer-field input { display: block; width: 100%; height: 46px; margin-top: 8px; padding: 0 13px; border: 1px solid #d9d0c8; border-radius: 7px; outline: none; background: #fff; color: var(--customer-ink); font: inherit; font-size: 14px; box-shadow: none; transition: border-color .15s, box-shadow .15s; }
.customer-field input::placeholder { color: #9b918a; opacity: 1; }
.customer-field input:focus { border-color: var(--customer-wine); box-shadow: 0 0 0 3px #8000000d; }
.customer-field input[aria-invalid="true"] { border-color: #ae271f; }
.customer-password { position: relative; }
.customer-password input { padding-right: 48px; }
.customer-password__toggle { position: absolute; top: 1px; right: 1px; display: grid; width: 44px; height: 44px; place-items: center; border: 0; border-radius: 6px; background: transparent; color: var(--customer-muted); cursor: pointer; }
.customer-password__toggle:hover { color: var(--customer-wine); }
.customer-link { color: var(--customer-wine); text-decoration: none; text-underline-offset: 3px; }
.customer-link:hover { text-decoration: underline; }
.customer-recovery { font-size: 11px; line-height: 1.5; }
.customer-remember { display: flex; align-items: center; gap: 9px; width: fit-content; min-height: 24px; margin-top: -3px; color: var(--customer-muted); font-size: 12px; cursor: pointer; }
.customer-remember input { width: 16px; height: 16px; margin: 0; border: 1px solid #c7bab0; border-radius: 4px; accent-color: var(--customer-wine); color: var(--customer-wine); }
.customer-submit { display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%; min-height: 47px; margin-top: -3px; padding: 12px 16px; border: 1px solid var(--customer-wine); border-radius: 7px; background: var(--customer-wine); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; transition: background-color .15s, border-color .15s; }
.customer-submit:hover:not(:disabled) { border-color: #600000; background: #600000; }
.customer-submit:disabled { opacity: .65; cursor: wait; }
.customer-register { display: flex; flex-wrap: wrap; justify-content: center; gap: 5px; margin: 23px 0 0; padding-top: 20px; border-top: 1px solid #f0e9e2; color: var(--customer-muted); font-size: 11px; line-height: 1.6; }
.customer-register .customer-link { font-weight: 600; }
.customer-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin: 20px 4px 0; color: var(--customer-muted); font-size: 10px; line-height: 1.5; }
.customer-back { display: inline-flex; align-items: center; gap: 7px; color: var(--customer-muted); text-decoration: none; }
.customer-back:hover { color: var(--customer-wine); }
.customer-status { margin-bottom: 20px; padding: 11px 13px; border: 1px solid #bed7bf; border-radius: 7px; background: #f1f8ef; color: #355a34; font-size: 12px; line-height: 1.6; }
.customer-error { margin: 7px 0 0; color: #a1251e; font-size: 12px; line-height: 1.5; }
.customer-spinner { width: 18px; height: 18px; border: 2px solid #ffffff50; border-top-color: #fff; border-radius: 50%; animation: customer-spin .8s linear infinite; }
.customer-login a:focus-visible, .customer-login button:focus-visible, .customer-remember input:focus-visible { outline: 2px solid var(--customer-wine); outline-offset: 4px; }
.customer-submit:focus-visible { outline-offset: 3px; }
@keyframes customer-spin { to { transform: rotate(360deg); } }
@media (max-width: 380px) {
    .customer-login { padding: 24px 16px; }
    .customer-brand { gap: 8px; font-size: 10px; letter-spacing: .06em; }
    .customer-heading { padding: 24px 22px 22px; }
    .customer-heading h1 { font-size: 26px; }
    .customer-heading__graphic { right: -27px; opacity: .65; }
    .customer-content { padding: 24px 22px; }
    .customer-eyebrow { font-size: 9px; }
    .customer-footer { font-size: 9px; }
}
@media (max-width: 600px) {
    .customer-field input { font-size: 16px; }
}
@media (prefers-reduced-motion: reduce) {
    .customer-field input, .customer-submit { transition: none; }
    .customer-spinner { animation: none; }
}
</style>
