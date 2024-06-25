<script setup>
import { Head, Link } from '@inertiajs/vue3';
import LayoutDefault from '@/Layouts/LayoutDefault.vue'

defineOptions({
    layout: LayoutDefault,
})

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    laravelVersion: {
        type: String,
        required: true,
    },
    phpVersion: {
        type: String,
        required: true,
    },
});


</script>

<template>
    <Head title="ПИЩЕПРОМ-СЕРВЕР: пищевое сырьё, пищевые ингредиенты и добавки, стабильно, каталог пищевых технологий" />

    <aside>
        <p>
            Категории
        </p>
    </aside>

    <div>
        <h1>
            Приветствуем Вас на ПИЩЕПРОМ-СЕРВЕРЕ
        </h1>
        <div class="flex flex-row">
            <div class="w-1/12 h-40 bg-yellow-400 border rounded">
                <div>Масло сливочное</div>
                <p>Маслo cладкoсливочное ГОCТ из кoровьeгo мoлoкa, бeз дoбaвoк, cнижaющиx себеcтoимocть!!
                Bысoкoэнергетический жировой продукт, поставщик полиненасыщенных жирных кислот, фосфолипидов, витаминов А, D и Е.</p>
                <div>
                    <span>758 руб/кг</span>
                </div>
            </div>
        </div>
    </div>
</template>
