<script setup>
import {useHead} from "@vueuse/head";
import {computed, onMounted, ref} from "vue";
import {useForm, Link} from "@inertiajs/vue3";
import axios from "axios";
import {route} from "ziggy-js";
import {useDate} from 'vuetify';
import {logo} from "@/Pages/Helpers/consts.js";
import {format} from "date-fns";
import VerwalterLayout from "@/Layouts/VerwalterLayout.vue";
defineOptions({
    layout: VerwalterLayout,
})

const date = useDate()

const tab = ref()
const tabsContacts = ref()
const tabsGeography = ref()
const tabsProducts = ref()
const tabsSegments = ref()
const tabsUnits = ref()

const brands = ref([])
const buildings = ref([])
const catalogs = ref([])
const categories = ref([])
const checks = ref([])
const cities = ref([])
const commodities = ref([])
const components = ref([])
const countries = ref([])
const emails = ref([])
const entities = ref([])
const entityClassifications = ref([])
const fields = ref([])
const good = ref(null)
const goods = ref([])
const labels = ref([])
const measures = ref([])
const products = ref([])
const purchases = ref([])
const regions = ref([])
const sale = ref()
const sales = ref([])
const segments = ref([])
const telephones = ref([])
const units = ref([])
const uris = ref([])

let manufacturers = ref();

const searchBrands = ref('')
const searchCities = ref('')
const searchGoods = ref('')
const searchProducts = ref('')
const searchUnits = ref('')
let searchComponents = ref('');

const dialogFormBuilding = ref(false)
const dialogFormCheck = ref(false)
const dialogFormCity = ref(false)
const dialogFormUnit = ref(false)
const dialogFormUri = ref(false)

const selectedLabelsIDs = ref([])
const selectedCategoriesIDs = ref([])

const headersCatalogs = ref([
    {
        title: 'name',
        key: 'name',
    },
    {
        title: 'uri',
        key: 'uri',
    },
    {
        title: 'rank',
        key: 'rank',
    },
])
const headerCountries = [
    {
        title: 'Флаг',
        key: 'flag',
    },
    {
        title: 'name',
        key: 'name',
    },
]
const headersProducts = [
    {
        title: 'rus',
        key: 'rus',
    },
    {
        title: 'eng',
        key: 'eng',
    },
    {
        title: 'Category',
        key: 'category',
    },
]
const headerRegions = ref([
    {
        title: 'Регион',
        key: 'name',
    },
    {
        title: 'Страна',
        key: 'country',
    },
    {
        title: 'Площадь',
        key: 'area',
    },
])
const headerTelephones = ref([
    {
        title: 'Number',
        key: 'number',
        align: 'start',
    },
    {
        title: 'Created',
        key: 'created_at',
        align: 'start',
    },
    {
        title: 'Owners',
        key: 'entities',
        align: 'start',
    },
])
const headerUris = [
    {
        title: 'Address',
        key: 'address',
        align: 'start',
    },
    {
        title: 'Valid',
        key: 'is_valid',
        align: 'center',
    },
    {
        title: 'Follow',
        key: 'follow',
        align: 'center',
    },
    {
        title: 'Design',
        key: 'has_brilliant_foremost_design',
        align: 'center',
    },
    {
        title: 'Owners',
        key: 'owners',
        align: 'start',
    },
    {
        title: 'created_at',
        key: 'created_at',
        align: 'start',
    },
]

//   B R A N D S
function indexBrands(){
    axios.get(route('brands.index')).then(function (response){
        brands.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const filteredBrands = computed(() => {
    const searchRequest = searchBrands.value.toLowerCase()
    return brands.value.filter(item =>
        item.name.toLowerCase().includes(searchRequest)
    )
})
// E N D  B U I L D I N G S



//   B U I L D I N G S
function indexBuildings(){
    axios.get(route('buildings.index')).then(function (response){
        buildings.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const searchBuildings = ref('')
const filteredBuildings = computed(()=>{
    const search = searchBuildings.value.toLowerCase();
    return buildings.value.filter(i => i.address.toLowerCase().includes(search))
})
const headerBuildings = ref([
    {
        title: 'Карта',
        key: 'city.yandexmapsgeo',
        align: 'center',
    },
    {
        title: 'Город',
        key: 'city.name',
        align: 'start',
    },
    {
        title: 'Адрес',
        key: 'address',
        align: 'start',
    },
    {
        title: 'Индекс',
        key: 'postcode',
        align: 'start',
    },
    {
        title: 'Units',
        key: 'units',
        align: 'start',
    },
])
const formBuilding = useForm({
    city_id: null,
    address: null,
    postcode: null,
})
function storeBuilding(){
    formBuilding.post(route('web.building.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formBuilding.reset()
            indexBuildings(searchBuildings.value)
        },
        onError: (errors) => {
            console.error('Form errors:', errors); // Log validation or server errors
        },
    })
}
// E N D  B U I L D I N G S



//   C A T A L O G S
function indexCatalogs(){
    axios.get(route('catalogs.index')).then(function (response){
        catalogs.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
//   C A T E G O R I E S
function indexCategories(){
    axios.get(route('categories.index')).then(function (response) {
        // handle success
        categories.value = response.data
    }).catch(function (error) {
        // handle error
        console.error(error);
    })
        .finally(function () {
            // always executed
        });
}
//   C H E C K S
function indexChecks(){
    axios.get(route('checks.index')).then(function (response){
        checks.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
//   C I T I E S
function indexCities(){
    axios.get(route('cities.index')).then(function (response){
        cities.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const filteredCities = computed(()=>{
    const string = searchCities.value.toLowerCase()
    return cities.value.filter(i => i.name.toLowerCase().includes(string))
})
// E N D  C I T I E S



//      C O M M O D I T I E S
function indexCommodities(){
    axios.get(route('commodities.index')).then(function (response){
        commodities.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const headerCommodities = ref([
    {
        key: 'name',
        title: 'Наименование',
        align: 'start',
        sortable: true,
    },
])
const searchCommodities = ref('')
const showFormCommodity = ref(false)
const formCommodity = useForm({
    name: null,
})
function storeCommodity(){
    formCommodity.post(route('api.commodity.store'), {
        replace: false,
        preserveState: false,
        preserveScroll: true,
        onSuccess: ()=> {
            formCommodity.reset()
            indexCommodities();
        },
    })
}
// E N D  C O M M O D I T I E S



//     C O M P O N E N T S
function indexComponents(){
    axios.get(route('components.index')).then(function (response){
        components.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const headerComponents = ref([
    {
        key: 'name',
        title: 'name',
        align: 'start',
        sortable: true,
    },
])
const formComponent = useForm({
    name: null,
})
function storeComponent(){
    formComponent.post(route('api.components.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formComponent.reset();
            indexComponents()
        },
    });
}
// E N D  C O M P O N E N T S



//   C O U N T R I E S
function indexCountries(){
    axios.get(route('countries.index')).then(function (response) {
        // handle success
        countries.value = response.data
    }).catch(function (error) {
        // handle error
        console.log(error);
    }).finally(function () {
        // always executed
    })
}
// E N D  C O U N T R I E S



//      E M A I L S
function indexEmails(){
    axios.get(route('emails.index')).then(function (response){
        emails.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
// E N D  E M A I L S



//   E N T I T I E S
function indexEntities(){
    axios.get(route('entities.index')).then(function (response){
        entities.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const headerEntities = [
    {
        title: 'name',
        key: 'name',
        align: 'start',
        width: '24%',
    },
    {
        title: 'Вид entity',
        key: 'classification.name',
        align: 'start',
    },
    {
        title: 'ИНН',
        key: 'INN',
        align: 'start',
    },
    {
        title: 'ОГРН',
        key: 'OGRN',
        align: 'start',
    },
    {
        title: 'Телефоны',
        key: 'telephones',
        align: 'start',
    },
    {
        key: 'emails',
        title: 'Emails',
        align: 'start',
        sortable: true,
    },
    {
        title: 'Buildings',
        key: 'buildings',
        align: 'start',
        width: '12%',
    },
    {
        title: 'Units',
        key: 'units',
        align: 'start',
    },
    {
        title: 'Города',
        key: 'cities',
        align: 'start',
    },
    {
        title: 'Страна',
        key: 'country.name',
        align: 'start',
    },
    {
        title: 'Chats',
        key: 'chats',
        align: 'start',
    },
]
const searchEntities = ref('')
const filteredEntities = computed(()=>{
    let filteredItems = entities.value

    if(searchEntities.value){
        const search = searchEntities.value.toLowerCase()
        filteredItems = filteredItems.filter(item => item.name.toLowerCase().includes(search))
    }

    return filteredItems
})
const dialogFormEntity = ref(false);
const formEntity = useForm({
    name: null,
    entity_classification_id: null,
    INN: null,
    OGRN: null,
    country_id: null,
    buildings: null,
    cities: null,
    emails: null,
    telephones: null,
    units: null,
})
function storeEntity(){
    formEntity.post(route('web.entity.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formEntity.reset()
            indexEntities()
        },
    })
}
//  E N D  E N T I T I E S



//     E N T I T Y  C L A S S I F I C A T I O N S
function indexEntityClassifications(){
    axios.get(route('entities-classification.index')).then(function (response){
        entityClassifications.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
// E N D  E N T I T Y  C L A S S I F I C A T I O N S



//      F I E L D S
function indexFields(){
    axios.get(route('fields.index')).then(function (response){
        fields.value = response.data
    }).catch(function (error){
        console.log(error)
    })
}
const headerFields = ref([
    {
        key: 'title',
        title: 'title',
        align: 'start',
        sortable: true,
    },
])
// E N D  F I E L D S



//   G O O D S
function indexGoods(){
    axios.get(route('goods.index')).then(function (response){
        goods.value = response.data
        // Проверяем, есть ли элементы в goods и берём первый
        if (goods.value && goods.value.length > 0) {
            fetchGood(goods.value[0].id);
        }
    }).catch(function (error){
        console.log(error)
    })
}
const headerGoods = [
    {
        title: 'Avatar',
        key: 'ava_image',
    },
    {
        title: 'name',
        key: 'name',
    },
]
function fetchGood(id){
    axios.get(route('good.fetch', id)).then(function (response){
        good.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const formGood = useForm({
    products: null,
    name: null,
    ava_image: null,
    denominator: null,
    description: null,
    is_published: true,
})
function storeGood(){
    formGood.post(route('web.good.store'), {
        replace: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formGood.reset()
            indexGoods()
        },
    });
}
// E N D  G O O D S



//   L A B E L S
function indexLabels(){
    axios.get(route('labels.index')).then(function (response) {
        labels.value = response.data
    }).catch(function (error) {
        console.log(error);
    });
}
//     M E A S U R E S
function indexMeasures(){
    axios.get(route('measures.index')).then(function (response){
        measures.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
//   P R O D U C T S
function indexProducts(){
    axios.get(route('products.index')).then(function (response) {
        // handle success
        products.value = response.data
    }).catch(function (error) {
        // handle error
        console.error(error);
    }).finally(function () {
        // always executed
    });
}
const filteredProducts = computed(()=>{
    const search = searchProducts.value.toLowerCase()
    return products.value.filter(i => i.rus.toLowerCase().includes(search) &&
        (selectedCategoriesIDs.value.length === 0 || selectedCategoriesIDs.value.includes(i.category_id)))
})
// Функция выбора категорий
const toggleCategories = (categoryId) => {
    if (selectedCategoriesIDs.value.includes(categoryId)) {
        selectedCategoriesIDs.value = selectedCategoriesIDs.value.filter((id) => id !== categoryId);
    } else {
        selectedCategoriesIDs.value.push(categoryId);
    }
};
//   P U R C H A S E S
function indexPurchases(){
    axios.get(route('purchases.index')).then(function (response){
        purchases.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
//   R E G I O N S
function indexRegions(){
    axios.get(route('regions.index')).then(function (response) {
        regions.value = response.data
    }).catch(function (error) {
        console.log(error);
    })
}
//     S A L E S
function indexSales(){
    axios.get(route('sales.index')).then(function (response){
        sales.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const headerSales = ref([
    {
        title: '+good',
        key: 'good',
        align: 'center',
        width: '33px',
    },
    {
        key: 'entity.name',
        title: 'Entity',
        sortable: true,
        align: 'start',
        class: 'text-primary',
        headerClass: 'bg-grey-lighten-3',
        width: '47%',
    },
    {
        key: 'date',
        title: 'Дата',
        sortable: true,
        align: 'start',
        class: 'text-rose-700',
        headerClass: 'bg-grey-lighten-3',
    },
    {
        key: 'total',
        title: 'Total',
        sortable: true,
        align: 'start',
        class: 'text-primary',
        headerClass: 'bg-grey-lighten-3',
    },
])
function showSale(id){
    axios.get(route('sales.show', id)).then(function (response){
        sale.value = response.data
    }).catch(function (error){
        console.log(error)
    });
}
const dialogFormSale = ref(false)
const formSale = useForm({
    date: null,
    entity_id: null,
    total: null,
})
function storeSale(){
    formSale.date = format(new Date(formSale.date), 'yyyy-MM-dd HH:mm:ss');
    formSale.post(route('web.sale.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formSale.reset()
            indexSales()
        },
    })
}
const dialogFormAttachGood = ref(false)
const loadingAttach = ref(false)
const snackbar = ref({
    show: false,
    text: '',
});
const formAttachGood = useForm({
    good_id: null,
    sale_id: null,
    quantity: null,
    measure_id: null,
    price: null,
})
function openAttachDialog(sale) {
    showSale(sale.id)
    formAttachGood.sale_id = sale.id
    dialogFormAttachGood.value = true;
}
function attachGood(){
    loadingAttach.value = true;
    formAttachGood.post(route('web.goodsale.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            snackbar.value = {
                show: true,
                text: 'Товар успешно привязан!',
            };
            dialogFormAttachGood.value = false; // Закрыть диалог
            formAttachGood.reset()
            indexSales()
        },
    })
}

let totalInKg = ref()
let quantity = ref()
// E N D  S A L E S



//   S E G M E N T S
function indexSegments(){
    axios.get(route('segments.index')).then(function (response){
        segments.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
// E N D  S E G M E N T S



//   T E L E P H O N E S
function indexTelephones(){
    axios.get(route('telephones.index')).then(function (response){
        telephones.value = response.data
    }).catch(function (error){
        console.error(error)
    })
}
const searchTelephones = ref('')
// const sortKey = ref('number'); // Ключ сортировки (например, 'number', 'name')
const sortOrder = ref('asc'); // Направление сортировки: 'asc' или 'desc'
const filteredTelephones = computed(()=>{
    const search = searchTelephones.value.toLowerCase()
    // Фильтрация
    let filtered = telephones.value.filter((t) =>
        t.number.toLowerCase().includes(search)
    )
    // Сортировка по number
    return filtered.sort((a, b) => {
        const valueA = a.number;
        const valueB = b.number;
        return sortOrder.value === 'asc'
            ? valueA.localeCompare(valueB)
            : valueB.localeCompare(valueA);
    })
})
// Метод для переключения сортировки
const toggleSort = () => {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
};
let dialogFormTelephone = ref(false)
const formTelephone = useForm({
    number: null,
})
function storeTelephone(){
    formTelephone.post(route('web.telephone.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formTelephone.reset()
            indexTelephones(searchTelephones.value)
        },
    })
}
// E N D  T E L E P H O N E S



//   U N I T S
function indexUnits(){
    axios.get(route('units.index')).then(function (response) {
        // handle success
        units.value = response.data
    })
        .catch(function (error) {
            // handle error
            console.error(error);
        });
}
// Фильтрация units
const filteredUnits = computed(() => {
    let filtered = units.value;

    // Фильтрация по поиску
    if (searchUnits.value) {
        const search = searchUnits.value.toLowerCase();
        filtered = filtered.filter(item =>
            item.name.toLowerCase().includes(search)
        );
    }

    // Фильтрация по labels
    if (selectedLabelsIDs.value.length > 0) {
        filtered = filtered.filter(unit => {
            // Предполагаем, что у unit есть массив labels с id
            const unitLabelIds = unit.labels?.map(label => label.id) || [];
            return selectedLabelsIDs.value.some(labelId =>
                unitLabelIds.includes(labelId)
            );
        });
    }

    return filtered;
})
const headerUnits = ref([
    {
        key: 'cities',
        title: 'Города',
        align: 'start',
        width: '14%',
    },
    {
        key: 'name',
        title: 'name',
    },
    {
        key: 'uris',
        title: 'Uris',
    },
    {
        key: 'fields',
        title: 'Fields',
        align: 'start',
        sortable: true,
        width: '16%',
    },
    {
        key: 'stages',
        title: 'Stages',
    },
])
const formUnit = useForm({
    name: null,
    fields: null,
    uris: null,
    labels: null,
    buildings: null,
});
function storeUnit(){
    formUnit.post(route('web.unit.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formUnit.reset()
            indexUnits()
        },
    });
}
// E N D  U N I T S



//   U R I S
function indexUris(){
    axios.get(route('uris.index')).then(function (response) {
        // handle success
        uris.value = response.data
    }).catch(function (error) {
        // handle error
        console.log(error);
    }).finally(function () {
        // always executed
    });
}
const formUri = useForm({
    address: null,
})
function storeUri(){
    formUri.post(route('web.uri.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: false,
        onSuccess: ()=> {
            formUri.reset();
            indexUris()
        },
    });
}
//     E N D  U R I S



//    C I T Y  S T O R E
const formCity = useForm({
    name: null,
    population: null,
    wiki: null,
    region_id: null,
    yandexmapsgeo: null,
    twogis: null,
    latitude: null,
    longitude: null,
})
function storeCity(){
    formCity.post(route('web.city.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formCity.reset();
            indexCities(searchCities.value)
        },
    })
}
//    S T O R E  C H E C K
const formCheck = useForm({
    date: null,
    entity_id: null,
    amount: null,
})
function storeCheck(){
    formCheck.post(route('web.check.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formCheck.reset()
            indexChecks()
        },
    });
}



//    P R O D U C T  S T O R E
const formProduct = useForm({
    rus: null,
    eng: null,
    zh: null,
    es: null,
    ar: null,
    po: null,
    de: null,
    fr: null,
    hi: null,
    category_id: null,
})
function storeProduct(){
    formProduct.post(route('web.product.store'), {
        replace: false,
        preserveState: true,
        preserveScroll: true,
        onSuccess: ()=> {
            formProduct.reset();
            indexProducts(searchProducts);
        },
    })
}



function getManufacturers(){
    axios.get(route('api.manufacturers')).then(function (response) {
        // handle success
        manufacturers.value = response.data;
    })
        .catch(function (error) {
            // handle error
            console.log(error);
        })
        .finally(function () {
            // always executed
        });
}

let email = ref('');
let message = ref('');
let successMessage = ref('');
async function sendMail() {
    try {
        const response = await axios.post('/api/mail', {
            'email': email.value,
            'message': message.value,
        });
        successMessage.value = response.data.message;
    } catch(error){
        console.log(error);
    }
}

onMounted(()=>{
    indexBrands()
    indexBuildings()
    indexCatalogs()
    indexCategories()
    indexChecks()
    indexCities()
    indexCommodities()
    indexComponents()
    indexCountries()
    indexEmails()
    indexEntities()
    indexEntityClassifications()
    indexFields()
    indexGoods()
    indexLabels()
    indexMeasures()
    indexProducts()
    indexPurchases()
    indexRegions()
    indexSales()
    indexSegments()
    indexTelephones()
    indexUnits()
    indexUris()

    getManufacturers();
})

useHead({
    title: `Управление торговлей`,
    meta: [
        {
            name: 'description',
            content: `Управление торговлей`,
        }
    ]
})

const toggleLabel = (labelId) => {
    if (selectedLabelsIDs.value.includes(labelId)) {
        selectedLabelsIDs.value = selectedLabelsIDs.value.filter((id) => id !== labelId);
    } else {
        selectedLabelsIDs.value.push(labelId)
    }
}

// Функция для генерации slug, если он отсутствует
const generateSlug = (name) => {
    return name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/(^-|-$)/g, "");
}

// Стили для активного состояния
const style = `
  .active {
    background-color: #0288d1; /* Цвет фона для активной категории */
    color: white; /* Цвет текста для активной категории */
  }
`;

// Функция для корректного отображения "Город - Адрес"
const formatBuildingTitle = (building) => {
    if (!building) return '';
    return `${building.city?.name || ' - '} , ${building.address}`;
};
</script>

<template>
    <v-theme-provider theme="dark">
        <v-container fluid>
            <v-row>
                <v-col>
                    <v-card>
                        <v-tabs v-model="tab">
                            <v-tab value="units">Объекты</v-tab>
                            <v-tab value="contacts">Контакты</v-tab>
                            <v-tab value="sales">Продажи</v-tab>
                            <v-tab value="purchases">Закупка</v-tab>
                            <v-tab value="products">Products</v-tab>
                            <v-tab value="geography">География</v-tab>
                            <v-tab value="segments">Классификаторы</v-tab>
                            <v-tab value="brands">Brands</v-tab>
                        </v-tabs>

                        <v-card-text>
                            <v-tabs-window v-model="tab">
                                <!--   О Б Ъ Е К Т Ы   -->
                                <v-tabs-window-item value="units">
                                    <v-tabs v-model="tabsUnits">
                                        <v-tab value="units_sub">Units</v-tab>
                                        <v-tab value="entities">Entities</v-tab>
                                    </v-tabs>
                                    <v-tabs-window v-model="tabsUnits">
                                        <v-tabs-window-item value="units_sub">
                                            <v-container fluid>
                                                <v-row>
                                                    <v-col>
                                                        <v-row>
                                                            <v-col lg="2">
                                                                <v-text-field v-model="searchUnits"
                                                                              label="Поиск по юнитам"
                                                                              variant="solo-inverted"
                                                                              density="compact"
                                                                              color="deep-orange-accent-3"
                                                                              hide-details
                                                                              class="border border-2 rounded border-rose-950"
                                                                ></v-text-field>
                                                            </v-col>
                                                            <v-col cols="1">
                                                                <v-btn text="+ Unit"
                                                                       @click="dialogFormUnit = !dialogFormUnit"
                                                                       variant="tonal"
                                                                       density="comfortable"
                                                                       color="deep-purple-darken-1"
                                                                ></v-btn>
                                                                <v-dialog v-model="dialogFormUnit"
                                                                          width="909">
                                                                    <v-card>
                                                                        <v-card-title>Form Unit</v-card-title>
                                                                        <v-card-text>
                                                                            <v-form @submit.prevent>
                                                                                <v-container>
                                                                                    <v-row>
                                                                                        <v-col>
                                                                                            <v-text-field v-model="formUnit.name"
                                                                                                          label="Name"
                                                                                                          variant="solo-filled"
                                                                                                          density="comfortable"
                                                                                                          hide-details
                                                                                            ></v-text-field>
                                                                                        </v-col>
                                                                                        <v-col lg="7">
                                                                                            <v-autocomplete v-model="formUnit.labels"
                                                                                                            :items="labels"
                                                                                                            :item-value="'id'"
                                                                                                            :item-title="'name'"
                                                                                                            label="Labels"
                                                                                                            variant="solo"
                                                                                                            density="comfortable"
                                                                                                            bg-color="blue-grey-darken-4"
                                                                                                            item-color="deep-orange-darken-2"
                                                                                                            multiple
                                                                                                            chips
                                                                                                            hide-details
                                                                                            ></v-autocomplete>
                                                                                        </v-col>
                                                                                    </v-row>
                                                                                    <v-row>
                                                                                        <v-col cols="9">
                                                                                            <v-autocomplete v-model="formUnit.uris"
                                                                                                            :items="uris"
                                                                                                            :item-value="'id'"
                                                                                                            :item-title="'address'"
                                                                                                            label="Uris selected"
                                                                                                            chips
                                                                                                            multiple
                                                                                            ></v-autocomplete>
                                                                                        </v-col>
                                                                                        <v-col cols="3">
                                                                                            <v-btn text="+ uri"
                                                                                                   @click="dialogFormUri = !dialogFormUri"
                                                                                            ></v-btn>
                                                                                            <v-dialog v-model="dialogFormUri"
                                                                                                      width="800"
                                                                                            >
                                                                                                <v-card>
                                                                                                    <v-toolbar title="FORM: Uri"></v-toolbar>
                                                                                                    <v-card-text>
                                                                                                        <v-form @submit.prevent>
                                                                                                            <v-row>
                                                                                                                <v-text-field v-model="formUri.address"
                                                                                                                              label="Uri address"
                                                                                                                              variant="outlined"
                                                                                                                ></v-text-field>
                                                                                                            </v-row>
                                                                                                            <v-row>
                                                                                                                <v-col cols="4">
                                                                                                                    <v-btn
                                                                                                                        text="store"
                                                                                                                        block
                                                                                                                        @click="storeUri"
                                                                                                                    ></v-btn>
                                                                                                                </v-col>
                                                                                                            </v-row>
                                                                                                        </v-form>
                                                                                                    </v-card-text>
                                                                                                </v-card>
                                                                                            </v-dialog>
                                                                                        </v-col>
                                                                                    </v-row>
                                                                                    <v-row>
                                                                                        <v-col>
                                                                                            <v-autocomplete v-model="formUnit.fields"
                                                                                                            :items="fields"
                                                                                                            :item-value="'id'"
                                                                                                            :item-title="'title'"
                                                                                                            label="Fields"
                                                                                                            placeholder="Выбери поле"
                                                                                                            variant="solo"
                                                                                                            density="comfortable"
                                                                                                            hide-details
                                                                                                            multiple
                                                                                                            chips
                                                                                                            bg-color="indigo-accent-4"></v-autocomplete>
                                                                                        </v-col>
                                                                                    </v-row>
                                                                                    <v-row>
                                                                                        <v-col>
                                                                                            <v-autocomplete v-model="formUnit.buildings"
                                                                                                            :items="buildings"
                                                                                                            :item-title="formatBuildingTitle"
                                                                                                            :item-value="'id'"
                                                                                                            label="Buildings"
                                                                                                            color="blue"
                                                                                                            multiple
                                                                                                            chips
                                                                                            ></v-autocomplete>
                                                                                        </v-col>
                                                                                    </v-row>
                                                                                </v-container>
                                                                            </v-form>
                                                                        </v-card-text>
                                                                        <v-card-actions class="justify-start">
                                                                            <v-btn
                                                                                text="Close"
                                                                                @click="isActive.value = false"
                                                                            ></v-btn>
                                                                            <v-btn text="Сохранить"
                                                                                   @click="storeUnit"
                                                                            ></v-btn>
                                                                        </v-card-actions>
                                                                    </v-card>
                                                                </v-dialog>
                                                            </v-col>
                                                        </v-row>
                                                        <v-row>
                                                            <v-col lg="8">
                                                                <v-data-table :items="filteredUnits"
                                                                              items-per-page="100"
                                                                              :headers="headerUnits"
                                                                              fixed-header
                                                                              height="939px"
                                                                              density="compact"
                                                                              class="border border-orange-800 rounded"
                                                                              hover
                                                                >
                                                                    <template v-slot:item.cities="{item}">
                                                                        <div v-for="building in item.buildings"
                                                                             class="text-sm font-sans text-lime-600 hover:text-lime-300"
                                                                        >{{building.city.name}}</div>
                                                                    </template>
                                                                    <template v-slot:item.name="{item}">
                                                                        <Link :href="route('web.unit.show', item.id)"
                                                                              class="text-sm"
                                                                        >
                                                                            {{item.name}}
                                                                        </Link>
                                                                    </template>
                                                                    <template v-slot:item.uris="{item}">
                                                                        <a v-for="uri in item.uris"
                                                                           :href="uri.address" target="_blank"
                                                                           class="text-xs inline-block mr-1 text-yellow-200">{{uri.address}}</a>
                                                                    </template>
                                                                    <template v-slot:item.fields="{item}">
                                                                        <div v-for="field in item.fields"
                                                                             class="text-[10px] font-sans"
                                                                        >{{field.title}}</div>
                                                                    </template>
                                                                    <template v-slot:item.stages="{item}">
                                                                        <v-chip v-for="stage in item.stages"
                                                                                size="x-small"
                                                                                color="teal-accent-4"
                                                                        >{{stage.name}}</v-chip>
                                                                    </template>
                                                                </v-data-table>
                                                            </v-col>
                                                            <v-col>
                                                                <v-sheet class="flex flex-row justify-normal flex-wrap">
                                                                    <v-btn
                                                                        v-for="label in labels"
                                                                        :key="label.id"
                                                                        v-model="selectedLabelsIDs"
                                                                        :color="selectedLabelsIDs.includes(label.id) ? 'cyan' : 'blue-grey-darken-2'"
                                                                        :class="{ 'active-btn': selectedLabelsIDs.includes(label.id) }"
                                                                        @click="toggleLabel(label.id)"
                                                                        size="x-small"
                                                                        class="ma-1"
                                                                    >
                                                                        <!-- текст -->
                                                                        {{ label.name }}

                                                                        <!-- бейдж сразу после текста -->
                                                                        <span class="ml-1 text-[8px] bg-teal-700 rounded">{{label.rank}}</span>
                                                                    </v-btn>
                                                                </v-sheet>
                                                            </v-col>
                                                        </v-row>
                                                    </v-col>
                                                </v-row>
                                            </v-container>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="entities">
                                            <v-container fluid>
                                                <v-row>
                                                    <v-col lg="2">
                                                        <v-text-field v-model="searchEntities"
                                                                      label="Поиск по entities"
                                                                      placeholder="By name"
                                                                      variant="outlined"
                                                                      density="compact"
                                                                      hide-details
                                                                      color="purple-darken-4"
                                                                      class="text-sm"
                                                        ></v-text-field>
                                                    </v-col>
                                                    <v-col lg="1">
                                                        <v-btn text="+"
                                                               @click="dialogFormEntity = !dialogFormEntity"
                                                               variant="elevated"
                                                               density="compact"
                                                               color="purple-darken-3"
                                                        ></v-btn>
                                                        <v-dialog v-model="dialogFormEntity"
                                                                  width="990"
                                                                  transition="dialog-top-transition"
                                                        >
                                                            <v-card>
                                                                <v-card-title>Form Entity</v-card-title>
                                                                <v-card-text>
                                                                    <v-form @submit.prevent>
                                                                        <v-row>
                                                                            <v-col cols="9">
                                                                                <v-text-field v-model="formEntity.name"
                                                                                              label="Name"
                                                                                              variant="outlined"
                                                                                              density="comfortable"
                                                                                              color="purple"
                                                                                              hide-details
                                                                                ></v-text-field>
                                                                            </v-col>
                                                                            <v-col cols="3">
                                                                                <v-select :items="entityClassifications"
                                                                                          :item-value="'id'"
                                                                                          :item-title="'name'"
                                                                                          v-model="formEntity.entity_classification_id"
                                                                                          label="Вид entity"
                                                                                          variant="solo"
                                                                                          density="comfortable"
                                                                                          class="text-sm"
                                                                                          hide-details
                                                                                ></v-select>
                                                                            </v-col>
                                                                        </v-row>
                                                                        <v-row>
                                                                            <v-col>
                                                                                <v-autocomplete :items="telephones"
                                                                                                :item-value="'id'"
                                                                                                :item-title="'number'"
                                                                                                v-model="formEntity.telephones"
                                                                                                label="Telephones"
                                                                                                placeholder="number without +7"
                                                                                                variant="outlined"
                                                                                                density="comfortable"
                                                                                                color="yellow-accent-1"
                                                                                                hide-details
                                                                                                multiple
                                                                                                chips
                                                                                ></v-autocomplete>
                                                                            </v-col>
                                                                            <v-col>
                                                                                <v-autocomplete :items="emails"
                                                                                                :item-value="'id'"
                                                                                                :item-title="'address'"
                                                                                                v-model="formEntity.emails"
                                                                                                label="Emails"
                                                                                                placeholder="email address"
                                                                                                variant="solo"
                                                                                                density="comfortable"
                                                                                                color="yellow-accent-1"
                                                                                                hide-details
                                                                                                multiple
                                                                                                chips
                                                                                ></v-autocomplete>
                                                                            </v-col>
                                                                            <v-col>
                                                                                <v-autocomplete :items="buildings"
                                                                                                :item-value="'id'"
                                                                                                :item-title="'address'"
                                                                                                v-model="formEntity.buildings"
                                                                                                multiple
                                                                                                label="Buildings"
                                                                                                placeholder="Адрес здания"
                                                                                                variant="outlined"
                                                                                                density="comfortable"
                                                                                                color="teal"
                                                                                                hide-details
                                                                                ></v-autocomplete>
                                                                            </v-col>
                                                                        </v-row>
                                                                        <v-row>
                                                                            <v-col>
                                                                                <v-text-field v-model="formEntity.INN"
                                                                                              label="ИНН"
                                                                                              placeholder="Введите цифры ИНН"
                                                                                              variant="solo"
                                                                                              density="comfortable"
                                                                                              color="indigo-accent-2"
                                                                                              hide-details></v-text-field>
                                                                            </v-col>
                                                                            <v-col>
                                                                                <v-text-field v-model="formEntity.OGRN"
                                                                                              label="ОГРН"
                                                                                              placeholder="Введите цифры ОГРН"
                                                                                              variant="solo"
                                                                                              density="comfortable"
                                                                                              color="indigo-accent-2"
                                                                                              hide-details
                                                                                ></v-text-field>
                                                                            </v-col>
                                                                        </v-row>
                                                                        <v-row>
                                                                            <v-col>
                                                                                <v-autocomplete :items="cities"
                                                                                                :item-value="'id'"
                                                                                                :item-title="'name'"
                                                                                                v-model="formEntity.cities"
                                                                                                label="Cities"
                                                                                                placeholder="Населенный пункт"
                                                                                                variant="solo"
                                                                                                density="comfortable"
                                                                                                color="grey"
                                                                                                hide-details
                                                                                                multiple
                                                                                ></v-autocomplete>
                                                                            </v-col>
                                                                            <v-col>
                                                                                <v-autocomplete :items="countries"
                                                                                                :item-value="'id'"
                                                                                                :item-title="'name'"
                                                                                                v-model="formEntity.country_id"
                                                                                                label="Country"
                                                                                                placeholder="Страна"
                                                                                                variant="filled"
                                                                                                density="comfortable"
                                                                                                color="purple-lighten-5"
                                                                                                hide-details
                                                                                ></v-autocomplete>
                                                                            </v-col>
                                                                        </v-row>
                                                                        <v-row>
                                                                            <v-col>
                                                                                <v-autocomplete :items="units"
                                                                                                :item-value="'id'"
                                                                                                :item-title="'name'"
                                                                                                v-model="formEntity.units"
                                                                                                label="Units"
                                                                                                placeholder="Привяжите к entity unit"
                                                                                                variant="outlined"
                                                                                                density="compact"
                                                                                                color="light-green-accent-3"
                                                                                                hide-details
                                                                                ></v-autocomplete>
                                                                            </v-col>
                                                                        </v-row>
                                                                    </v-form>
                                                                </v-card-text>
                                                                <v-card-actions>
                                                                    <v-btn @click="storeEntity"
                                                                           text="сохранить"
                                                                           variant="flat"
                                                                    ></v-btn>
                                                                </v-card-actions>
                                                            </v-card>
                                                        </v-dialog>
                                                    </v-col>
                                                </v-row>
                                                <v-row>
                                                    <v-col>
                                                        <v-data-table :items="filteredEntities"
                                                                      items-per-page="160"
                                                                      :headers="headerEntities"
                                                                      fixed-header
                                                                      height="875px"
                                                                      fixed-footer
                                                                      density="compact"
                                                                      hover
                                                                      class="border rounded"
                                                        >
                                                            <template v-slot:item.telephones="{item}">
                                                                <div v-for="telephone in item.telephones">
                                                                    {{telephone.number}}
                                                                </div>
                                                            </template>
                                                            <template v-slot:item.buildings="{item}">
                                                                <div v-for="building in item.buildings"
                                                                     class="text-xs"
                                                                >
                                                                    {{building.address}}
                                                                </div>
                                                            </template>
                                                            <template v-slot:item.units="{item}">
                                                                <div v-for="unit in item.units"
                                                                     class="text-xs"
                                                                >
                                                                    {{unit.name}}
                                                                </div>
                                                            </template>
                                                            <template v-slot:item.cities="{item}">
                                                                <div v-for="city in item.cities">
                                                                    {{city.name}}
                                                                </div>
                                                            </template>
                                                            <template v-slot:item.chats="{item}">
                                                                <div v-for="chat in item.chats"
                                                                     class="text-[8px]"
                                                                >
                                                                    {{chat.numbers}}
                                                                </div>
                                                            </template>
                                                        </v-data-table>
                                                    </v-col>
                                                </v-row>
                                            </v-container>
                                        </v-tabs-window-item>
                                    </v-tabs-window>
                                </v-tabs-window-item>
                                <!--           E N D  U N I T S           -->






                                <!--           S A L E S           -->
                                <v-tabs-window-item value="sales">
                                    <v-container>
                                        <v-row>
                                            <v-col cols="1">
                                                <v-btn text="Продать"
                                                       @click="dialogFormSale = !dialogFormSale"
                                                       variant="elevated"
                                                       density="comfortable"
                                                       color="indigo"
                                                ></v-btn>
                                                <v-dialog v-model="dialogFormSale"
                                                          width="808"
                                                >
                                                    <v-card>
                                                        <v-card-title>Form Sale</v-card-title>
                                                        <v-card-text>
                                                            <v-form @submit.prevent>
                                                                <v-row>
                                                                    <v-col>
                                                                        <v-autocomplete :items="entities"
                                                                                        :item-value="'id'"
                                                                                        :item-title="'name'"
                                                                                        v-model="formSale.entity_id"
                                                                                        density="compact"
                                                                                        variant="outlined"
                                                                                        chips
                                                                        ></v-autocomplete>
                                                                    </v-col>
                                                                </v-row>
                                                                <v-row>
                                                                    <v-col>
                                                                        <v-date-picker v-model="formSale.date"></v-date-picker>
                                                                    </v-col>
                                                                    <v-col>
                                                                        <v-text-field v-model="formSale.total"
                                                                                      label="Total"
                                                                                      placeholder="Сумма продажи"
                                                                                      density="comfortable"
                                                                                      variant="solo"
                                                                        ></v-text-field>
                                                                    </v-col>
                                                                </v-row>
                                                            </v-form>
                                                        </v-card-text>
                                                        <v-card-actions>
                                                            <v-btn text="store"
                                                                   @click="storeSale"
                                                                   variant="elevated"
                                                                   density="comfortable"
                                                                   color="purple"
                                                            ></v-btn>
                                                        </v-card-actions>
                                                    </v-card>
                                                </v-dialog>
                                            </v-col>
                                        </v-row>
                                        <v-row>
                                            <v-col cols="9">
                                                <v-data-table :items="sales"
                                                              items-per-page="365"
                                                              :headers="headerSales"
                                                              fixed-header
                                                              height="700px"
                                                              density="compact"
                                                              hover
                                                >
                                                    <template v-slot:item.good="{item}">
                                                        <v-btn text="+"
                                                               @click="openAttachDialog(item)"
                                                               variant="elevated"
                                                               color="grey"
                                                               density="compact"
                                                               size="20"
                                                        ></v-btn>
                                                    </template>
                                                    <template v-slot:item.date="{item}">
                                                        <span class="text-xs font-BadScript">{{date.format(item.date, 'fullDateWithWeekday')}}</span>
                                                    </template>
                                                    <template v-slot:item.entity.name="{item}">
                                                        <span class="text-sm font-RobotoRegular">{{item.entity.name}}</span>
                                                    </template>
                                                    <template v-slot:item.total="{item}">
                                                        <span @click="showSale(item.id)"
                                                              class="cursor-pointer"
                                                        >
                                                            {{item.total}}</span>
                                                    </template>
                                                </v-data-table>
                                                <v-dialog v-model="dialogFormAttachGood"
                                                          transition="dialog-bottom-transition"
                                                          width="1000"
                                                >
                                                    <template v-slot:default="{isActive}">
                                                        <v-card theme="dark">
                                                            <v-card-title>Form Attach Good</v-card-title>
                                                            <v-card-text class="text-red-700">
                                                                <v-row>
                                                                    <v-col cols="7">
                                                                        <v-form @submit.prevent>
                                                                            <v-row>
                                                                                <v-col>
                                                                                    <v-autocomplete :items="goods"
                                                                                                    :item-value="'id'"
                                                                                                    :item-title="'name'"
                                                                                                    v-model="formAttachGood.good_id"
                                                                                                    label="Good"
                                                                                                    variant="outlined"
                                                                                                    @change="fetchGood(formAttachGood.good_id)"
                                                                                    ></v-autocomplete>
                                                                                </v-col>
                                                                            </v-row>
                                                                            <v-row>
                                                                                <v-col cols="3">
                                                                                    <v-text-field v-model="formAttachGood.quantity"
                                                                                                  label="Количество"
                                                                                                  variant="outlined"
                                                                                                  density="compact"
                                                                                                  theme="dark"
                                                                                                  hide-details
                                                                                    ></v-text-field>
                                                                                </v-col>
                                                                                <v-col cols="3">
                                                                                    <v-select :items="measures"
                                                                                              :item-value="'id'"
                                                                                              :item-title="'name'"
                                                                                              v-model="formAttachGood.measure_id"
                                                                                              label="Measures"
                                                                                              variant="outlined"
                                                                                              density="compact"
                                                                                              hide-details
                                                                                    ></v-select>
                                                                                </v-col>
                                                                                <v-col cols="6">
                                                                                    <v-text-field v-model="formAttachGood.price"
                                                                                                  label="Price"
                                                                                                  variant="outlined"
                                                                                                  density="compact"
                                                                                                  hide-details
                                                                                    ></v-text-field>
                                                                                </v-col>
                                                                            </v-row>
                                                                        </v-form>
                                                                        <v-row>
                                                                            <v-col>
                                                                                <label>Denominator</label><span>{{good.denominator}}</span>
                                                                            </v-col>
                                                                            <v-col>
                                                                                <v-text-field v-model="totalInKg"
                                                                                              label="total in kg"
                                                                                              variant="solo"
                                                                                              density="compact"
                                                                                              hide-details
                                                                                ></v-text-field>
                                                                            </v-col>
                                                                            <v-col>
                                                                                <label class="text-sm font-sans">Кол-во шт</label>
                                                                                <span>{{quantity = totalInKg / good.denominator}}</span>
                                                                            </v-col>
                                                                            <v-col>
                                                                                <label>Цена шт</label>
                                                                                <span>{{good.total / quantity}}</span>
                                                                            </v-col>
                                                                        </v-row>
                                                                    </v-col>
                                                                    <v-col cols="5">
                                                                        <v-sheet>
                                                                            <v-row>
                                                                                <v-col>
                                                                                    <div><label>Total</label>{{sale.total}}</div>
                                                                                </v-col>
                                                                                <v-col>
                                                                                    <label>Position Sum</label><span>{{formAttachGood.quantity * formAttachGood.price}}</span>
                                                                                </v-col>
                                                                            </v-row>
                                                                            <v-row>
                                                                                <v-col>
                                                                                    <div class="text-[9px]">{{good.name}}</div>
                                                                                </v-col>
                                                                            </v-row>
                                                                        </v-sheet>
                                                                    </v-col>
                                                                </v-row>
                                                            </v-card-text>
                                                            <v-card-actions>
                                                                <v-spacer />
                                                                <v-btn text="attach"
                                                                       @click="attachGood"
                                                                       variant="text"
                                                                       density="compact"
                                                                       color="teal-lighten-2"
                                                                ></v-btn>
                                                            </v-card-actions>
                                                        </v-card>
                                                    </template>
                                                </v-dialog>
                                                <!-- ✅ Уведомление об успехе -->
                                                <v-snackbar v-model="snackbar.show" :timeout="2000" color="green">
                                                    {{ snackbar.text }}
                                                </v-snackbar>
                                            </v-col>
                                            <v-col cols="3">
                                                <v-list v-if="good"
                                                        density="compact"
                                                        class="rounded bg-slate-700"
                                                >
                                                    <v-list-item v-for="good in sale?.goods ?? []">
                                                        <v-container>
                                                            <v-row>
                                                                <v-col>
                                                                    <span class="cursor-pointer text-sm font-ComfortaaVariableFont">
                                                                        <Link :href="route('Ameise.good.show', good.id)">
                                                                            {{good.name}}
                                                                        </Link>
                                                                    </span>
                                                                </v-col>
                                                            </v-row>
                                                            <v-row>
                                                                <v-col>
                                                                    <span class="text-lg font-sans">{{good.pivot.price}}</span>
                                                                </v-col>
                                                                <v-col cols="2">
                                                                    <span class="text-[10px] font-serif">{{good.pivot.quantity}}</span>
                                                                </v-col>
                                                                <v-col>
                                                                    <span class="text-sm">{{good.pivot.total}}</span>
                                                                </v-col>
                                                            </v-row>
                                                        </v-container>
                                                    </v-list-item>
                                                </v-list>
                                            </v-col>
                                        </v-row>
                                    </v-container>
                                </v-tabs-window-item>
                                <!--      E N D  S A L E S      -->









                                <!--           G E O G R A P H Y           -->
                                <v-tabs-window-item value="geography">
                                    <v-tabs v-model="tabsGeography">
                                        <v-tab value="cities">Cities</v-tab>
                                        <v-tab value="buildings">Buildings</v-tab>
                                        <v-tab value="regions">Regions</v-tab>
                                        <v-tab value="countries">Countries</v-tab>
                                    </v-tabs>
                                    <v-tabs-window v-model="tabsGeography">
                                        <v-tabs-window-item value="cities">
                                            <v-row>
                                                <v-col lg="3">
                                                    <v-text-field v-model="searchCities"
                                                                  label="Поиск по городам"
                                                                  variant="solo"
                                                                  density="compact"
                                                                  color="purple-lighten-4"
                                                                  hide-details
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="1">
                                                    <v-btn text="+ 🏰"
                                                           @click="dialogFormCity = !dialogFormCity"
                                                           variant="tonal"
                                                           density="compact"></v-btn>
                                                    <v-dialog v-model="dialogFormCity"
                                                              width="900"
                                                    >
                                                        <v-card>
                                                            <v-card-title>Form City</v-card-title>
                                                            <v-card-text>
                                                                <v-form @submit.prevent>
                                                                    <v-row>
                                                                        <v-col lg="9">
                                                                            <v-text-field v-model="formCity.wiki"
                                                                                          label="Wiki"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                                          size="small"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                        <v-col lg="3">
                                                                            <v-text-field v-model="formCity.population"
                                                                                          label="Население"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col lg="6">
                                                                            <v-text-field v-model="formCity.name"
                                                                                          label="Название"
                                                                                          variant="solo"
                                                                                          density="comfortable"
                                                                                          color="indigo-lighten-4"
                                                                                          class="font-UnderdogRegular"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                        <v-col lg="6">
                                                                            <v-autocomplete :items="regions"
                                                                                            :item-title="'name'"
                                                                                            :item-value="'id'"
                                                                                            v-model="formCity.region_id"
                                                                                            label="Регион"
                                                                                            variant="filled"
                                                                                            density="comfortable"
                                                                                            color="purple"
                                                                            ></v-autocomplete>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-text-field v-model="formCity.yandexmapsgeo"
                                                                                          label="yandex.ru/maps/geo/"
                                                                                          variant="solo"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-text-field v-model="formCity.latitude"
                                                                                          label="Широта"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                        <v-col>
                                                                            <v-text-field v-model="formCity.longitude"
                                                                                          label="Долгота"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-text-field v-model="formCity.twogis"
                                                                                          label="2GIS"
                                                                                          variant="solo"
                                                                                          density="compact"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                </v-form>
                                                            </v-card-text>
                                                            <v-card-actions>
                                                                <v-btn @click="storeCity"
                                                                       text="сохранить"
                                                                       variant="elevated"
                                                                       color="green">
                                                                </v-btn>
                                                            </v-card-actions>
                                                        </v-card>
                                                    </v-dialog>
                                                </v-col>
                                            </v-row>
                                            <v-row>
                                                <v-col>
                                                    <v-sheet>
                                                        <div v-for="(city, index) in filteredCities"
                                                             :key="city.id"
                                                             class="inline-block mr-1 p-1 rounded text-xs text-teal-200 hover:bg-teal-200 hover:text-black"
                                                        >
                                                            <div>
                                                                <a :href="city.yandexmapsgeo" target="_blank"
                                                                   class="inline-flex items-center justify-center mr-1 bg-teal-500 text-white rounded-full text-[6px] font-bold w-3 h-3"
                                                                >{{ index + 1 }}</a>
                                                                <Link :href="route('city.show', city.id)">
                                                                    {{city.name}}
                                                                </Link>
                                                            </div>
                                                            <div class="text-[7px] text-teal-500">{{city.region.name}}</div>
                                                        </div>
                                                    </v-sheet>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="buildings">
                                            <v-row>
                                                <v-col cols="1">
                                                    <div class="flex flex-row justify-center font-sans text-sm">{{ filteredBuildings.length }}</div>
                                                </v-col>
                                                <v-col lg="3">
                                                    <v-text-field v-model="searchBuildings"
                                                                  label="Искать по адресам"
                                                                  variant="solo"
                                                                  density="compact"
                                                                  hide-details
                                                    ></v-text-field>
                                                </v-col>
                                                <v-col cols="1">
                                                    <v-btn text="+ 🛣️"
                                                           @click="dialogFormBuilding = !dialogFormBuilding"
                                                           variant="tonal"
                                                           density="compact"></v-btn>
                                                    <v-dialog v-model="dialogFormBuilding"
                                                              width="750"
                                                    >
                                                        <v-card>
                                                            <v-card-title>Form Building</v-card-title>
                                                            <v-card-text>
                                                                <v-form @submit.prevent>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-text-field v-model="formBuilding.address"
                                                                                          label="Адрес здания"
                                                                                          variant="outlined"
                                                                                          density="comfortable"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-col>
                                                                            <v-autocomplete :items="cities"
                                                                                            :item-value="'id'"
                                                                                            :item-title="'name'"
                                                                                            v-model="formBuilding.city_id"
                                                                                            label="Населенный пункт"
                                                                                            variant="solo"
                                                                                            density="comfortable"
                                                                            ></v-autocomplete>
                                                                        </v-col>
                                                                        <v-col>
                                                                            <v-text-field v-model="formBuilding.postcode"
                                                                                          label="Postcode"
                                                                                          variant="solo"
                                                                                          density="comfortable"
                                                                                          color="grey"
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                    </v-row>
                                                                </v-form>
                                                            </v-card-text>
                                                            <v-card-actions>
                                                                <v-divider vertical
                                                                           thickness="1"
                                                                           opacity="1"
                                                                ></v-divider>
                                                                <v-btn text="store"
                                                                       @click="storeBuilding"
                                                                       variant="elevated"
                                                                       density="compact"></v-btn>
                                                            </v-card-actions>
                                                        </v-card>
                                                    </v-dialog>
                                                </v-col>
                                            </v-row>
                                            <v-row>
                                                <v-col>
                                                    <v-data-table :items="filteredBuildings"
                                                                  items-per-page="100"
                                                                  :headers="headerBuildings"
                                                                  fixed-header
                                                                  height="900px"
                                                                  density="compact"
                                                                  hover>
                                                        <template v-slot:item.city.yandexmapsgeo="{item}">
                                                            <a :href="item.city.yandexmapsgeo" target="_blank"
                                                               class="inline-flex items-center justify-center mr-1 bg-teal-500 text-white rounded-full text-[6px] font-bold w-3 h-3"
                                                            >Y</a>
                                                        </template>
                                                        <template v-slot:item.postcode="{item}">
                                                            <span class="font-Screpka text-xl">{{item.postcode}}</span>
                                                        </template>
                                                        <template v-slot:item.units="{item}">
                                                            <div v-for="unit in item.units"
                                                                 class="text-xs"
                                                            >
                                                                {{unit.name}}
                                                            </div>
                                                        </template>
                                                    </v-data-table>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="regions">
                                            <v-row>
                                                <v-col>
                                                    <v-data-table :items="regions"
                                                                  :headers="headerRegions"
                                                                  items-per-page="100"
                                                                  density="compact"
                                                                  hover="hover"
                                                    >
                                                        <template v-slot:item.country="{item}">
                                                            {{item.country.name}}
                                                        </template>
                                                    </v-data-table>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="countries">
                                            <v-row>
                                                <v-col>
                                                    <v-data-table :items="countries"
                                                                  :headers="headerCountries"
                                                                  items-per-page="47"
                                                                  density="compact"
                                                                  hover="hover"
                                                    >
                                                        <template v-slot:item.flag="{item}">
                                                            <v-img :src="item.flag"
                                                                   width="51"
                                                                   class="border border-1 border-gray-200"
                                                            ></v-img>
                                                        </template>
                                                    </v-data-table>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                    </v-tabs-window>
                                </v-tabs-window-item>
                                <!--      E N D  G E O G R A P H Y      -->







                                <!--   P R O D U C T S   -->
                                <v-tabs-window-item value="products">
                                    <v-tabs v-model="tabsProducts">
                                        <v-tab value="categories_products">Products</v-tab>
                                        <v-tab value="goods">Goods</v-tab>
                                        <v-tab value="components">Components</v-tab>
                                        <v-tab value="commodities">Commodities</v-tab>
                                    </v-tabs>
                                    <v-tabs-window v-model="tabsProducts">
                                        <v-tabs-window-item value="categories_products">
                                            <v-row>
                                                <v-col lg="3">
                                                    <v-list variant="outlined"
                                                            density="compact"
                                                            rounded
                                                    >
                                                        <v-list-item v-for="category in categories"
                                                                     :key="category.id"
                                                                     :class="{ 'active': selectedCategoriesIDs.includes(category.id) }"
                                                                     @click="toggleCategories(category.id)"
                                                                     size="x-small"
                                                                     class="ma-1 hover:bg-sky-700 hover:text-indigo-400"
                                                        >
                                                            <span>{{category.name}}</span>
                                                        </v-list-item>
                                                    </v-list>
                                                </v-col>
                                                <v-col>
                                                    <v-card flat>
                                                        <v-card-text>
                                                            <v-data-table :items="filteredProducts"
                                                                          :headers="headersProducts"
                                                                          items-per-page="51"
                                                                          density="compact"
                                                                          hover="hover"
                                                            >
                                                                <template v-slot:top>
                                                                    <v-row>
                                                                        <v-col cols="9">
                                                                            <v-text-field v-model="searchProducts"
                                                                                          label="Filter products"
                                                                                          prepend-inner-icon="mdi-magnify"
                                                                                          variant="outlined"
                                                                                          density="compact"
                                                                                          clearable
                                                                            ></v-text-field>
                                                                        </v-col>
                                                                        <v-col cols="3">
                                                                            <v-dialog width="750"
                                                                            >
                                                                                <template v-slot:activator="{props}">
                                                                                    <v-btn text="+ product"
                                                                                           v-bind="props"
                                                                                           variant="tonal"
                                                                                           color="indigo"
                                                                                    ></v-btn>
                                                                                </template>
                                                                                <v-card>
                                                                                    <v-card-text>
                                                                                        <v-form @submit.prevent>
                                                                                            <v-row>
                                                                                                <v-col cols="7">
                                                                                                    <v-text-field v-model="formProduct.rus"
                                                                                                                  label="Продукт"
                                                                                                                  variant="outlined"
                                                                                                                  class="bg-pink-800"
                                                                                                                  hide-details
                                                                                                    ></v-text-field>
                                                                                                </v-col>
                                                                                                <v-col cols="5">
                                                                                                    <v-autocomplete :items="categories"
                                                                                                                    :item-value="'id'"
                                                                                                                    :item-title="'name'"
                                                                                                                    v-model="formProduct.category_id"
                                                                                                                    label="Category"
                                                                                                                    variant="outlined"
                                                                                                                    density="comfortable"
                                                                                                                    color="indigo"></v-autocomplete>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.eng"
                                                                                                                  label="Product eng"
                                                                                                    ></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.zh"
                                                                                                                  label="Product zh"
                                                                                                    ></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.es"
                                                                                                                  label="Product es"
                                                                                                    ></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.ar"
                                                                                                                  label="Product ar"
                                                                                                                  variant="solo-filled"
                                                                                                                  density="comfortable"
                                                                                                                  color="red"></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.po"
                                                                                                                  label="Product po"
                                                                                                                  variant="solo-filled"
                                                                                                                  density="comfortable"
                                                                                                                  color="red"></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                            <v-row>
                                                                                                <v-col>
                                                                                                    <v-text-field v-model="formProduct.de"
                                                                                                                  label="Product de"
                                                                                                                  variant="solo"
                                                                                                                  density="comfortable"
                                                                                                                  color="cyan-lighten-3"></v-text-field>
                                                                                                </v-col>
                                                                                            </v-row>
                                                                                        </v-form>
                                                                                    </v-card-text>
                                                                                    <v-card-actions>
                                                                                        <v-spacer></v-spacer>
                                                                                        <v-divider vertical></v-divider>
                                                                                        <v-btn
                                                                                            color="blue-darken-1"
                                                                                            variant="text"
                                                                                            @click="close"
                                                                                        >
                                                                                            Cancel
                                                                                        </v-btn>
                                                                                        <v-divider vertical></v-divider>

                                                                                        <v-btn text="store"
                                                                                               @click="storeProduct"
                                                                                               color="blue-darken-1"
                                                                                               variant="elevated"
                                                                                        ></v-btn>
                                                                                    </v-card-actions>
                                                                                </v-card>
                                                                            </v-dialog>
                                                                        </v-col>
                                                                    </v-row>
                                                                </template>
                                                                <template v-slot:item.rus="{item}">
                                                                    <Link :href="route('product.show', item.id)"
                                                                          color="amber-accent-2"
                                                                          class="font-UnderdogRegular text-sm"
                                                                    >
                                                                        {{item.rus}}
                                                                    </Link>
                                                                </template>
                                                                <template v-slot:item.category="{item}">
                                                                    <span>{{item.category.name}}</span>
                                                                </template>
                                                            </v-data-table>
                                                        </v-card-text>
                                                    </v-card>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="goods">
                                            <v-row>
                                                <v-col>
                                                    <v-row>
                                                        <v-col cols="4">
                                                            <v-text-field v-model="searchGoods"
                                                                          label="Поиск: Товары"
                                                                          variant="solo"
                                                                          density="comfortable"
                                                                          hide-details
                                                            ></v-text-field>
                                                        </v-col>
                                                        <v-col cols="3">
                                                            <v-dialog transition="dialog-top-transition"
                                                                      width="900"
                                                            >
                                                                <template v-slot:activator="{ props: activatorProps }">
                                                                    <v-btn
                                                                        v-bind="activatorProps"
                                                                        text="Новый товар"
                                                                        block
                                                                    ></v-btn>
                                                                </template>
                                                                <template v-slot:default="{ isActive }">
                                                                    <v-card>
                                                                        <v-card-title>Form Good</v-card-title>
                                                                        <v-card-text>
                                                                            <v-form @submit.prevent>
                                                                                <v-row>
                                                                                    <v-col cols="12">
                                                                                        <v-autocomplete :items="products"
                                                                                                        :item-title="'rus'"
                                                                                                        :item-value="'id'"
                                                                                                        v-model="formGood.products"
                                                                                                        label="Products"
                                                                                                        placeholder="Выбери Product"
                                                                                                        density="compact"
                                                                                                        variant="outlined"
                                                                                                        color="red"
                                                                                                        multiple
                                                                                        ></v-autocomplete>
                                                                                    </v-col>
                                                                                </v-row>
                                                                                <v-row>
                                                                                    <v-col cols="8">
                                                                                        <v-text-field v-model="formGood.name"
                                                                                                      label="Good name"
                                                                                                      variant="outlined"
                                                                                        ></v-text-field>
                                                                                    </v-col>
                                                                                    <v-col cols="2">
                                                                                        <v-text-field v-model="formGood.denominator"
                                                                                                      label="Denominator"
                                                                                                      variant="solo"
                                                                                                      density="comfortable"
                                                                                                      hide-details></v-text-field>
                                                                                    </v-col>
                                                                                    <v-col cols="2">
                                                                                        <v-checkbox v-model="formGood.is_published"></v-checkbox>
                                                                                    </v-col>
                                                                                </v-row>
                                                                                <v-row>
                                                                                    <v-file-input v-model="formGood.ava_image"
                                                                                                  label="Good's avatar"
                                                                                                  chips
                                                                                    ></v-file-input>
                                                                                </v-row>
                                                                                <v-row>
                                                                                    <v-col>
                                                                                        <v-textarea v-model="formGood.description"
                                                                                                    label="Description"
                                                                                                    variant="outlined"
                                                                                                    density="default"
                                                                                                    hide-details
                                                                                        ></v-textarea>
                                                                                    </v-col>
                                                                                </v-row>
                                                                            </v-form>
                                                                        </v-card-text>
                                                                        <v-card-actions>
                                                                            <v-spacer></v-spacer>
                                                                            <v-divider></v-divider>

                                                                            <v-btn text="save"
                                                                                   @click="storeGood"
                                                                                   variant="plain"
                                                                                   color="indigo-darken-4"
                                                                            ></v-btn>
                                                                        </v-card-actions>
                                                                    </v-card>
                                                                </template>
                                                            </v-dialog>
                                                        </v-col>
                                                    </v-row>
                                                    <v-row>
                                                        <v-data-table :items="goods"
                                                                      :headers="headerGoods"
                                                                      :search="searchGoods"
                                                                      items-per-page="90"
                                                                      density="compact"
                                                                      hover="hover"
                                                        >
                                                            <template v-slot:item.ava_image="{item}">
                                                                <v-badge :content="item.id"
                                                                         color="teal"
                                                                >
                                                                    <v-img :src="item.ava_image || logo"
                                                                           @click="fetchGood(item.id)"
                                                                           alt="Avatar"
                                                                           width="50"
                                                                           height="50"
                                                                           cover
                                                                           class="rounded"
                                                                    ></v-img>
                                                                </v-badge>
                                                            </template>
                                                            <template v-slot:item.name="{ item }">
                                                                <Link :href="route('Ameise.good.show', { id: item.id, slug: item.slug || generateSlug(item.name) })"
                                                                      class="text-decoration-none"
                                                                >
                                                                    {{ item.name }}
                                                                </Link>
                                                            </template>
                                                        </v-data-table>
                                                    </v-row>
                                                </v-col>
                                                <v-col lg="3">
                                                    <v-sheet>
                                                        <v-row>
                                                            <v-col>
                                                                <span v-if="good && good.name">{{ good.name }}</span>
                                                                <span v-else>Загрузка...</span>
                                                            </v-col>
                                                        </v-row>
                                                    </v-sheet>
                                                </v-col>
                                            </v-row>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="components">
                                            <v-container fluid>
                                                <v-row>
                                                    <v-col>
                                                        <v-text-field v-model="searchComponents"
                                                                      label="search components"
                                                                      variant="solo"
                                                                      density="comfortable"
                                                                      clearable
                                                                      hide-details
                                                                      class="border rounded"
                                                        ></v-text-field>
                                                    </v-col>
                                                </v-row>
                                                <v-row>
                                                    <v-col>
                                                        <v-data-table :items="components"
                                                                      items-per-page="150"
                                                                      :headers="headerComponents"
                                                                      fixed-header
                                                                      height="810px"
                                                                      density="compact"
                                                                      hover
                                                                      class="border rounded border-lime-300"
                                                        ></v-data-table>
                                                    </v-col>
                                                </v-row>
                                            </v-container>
                                        </v-tabs-window-item>
                                        <v-tabs-window-item value="commodities">
                                            <v-container fluid>
                                                <v-row>
                                                    <v-col cols="9">
                                                        <v-text-field v-model="searchCommodities"
                                                                      @input="indexCommodities(searchCommodities)"
                                                                      label="Commodities"
                                                                      placeholder="search"
                                                                      variant="outlined"
                                                                      density="compact"
                                                                      hide-details
                                                        ></v-text-field>
                                                    </v-col>
                                                    <v-col cols="3">
                                                        <v-btn text="+ commodity"
                                                               @click="showFormCommodity = !showFormCommodity"
                                                               variant="elevated"
                                                               color="grey"
                                                        ></v-btn>
                                                        <v-dialog v-model="showFormCommodity"
                                                                  width="800"
                                                        >
                                                            <template v-slot:default="{isActive}">
                                                                <v-card>
                                                                    <v-card-title>Form Commodity</v-card-title>
                                                                    <v-card-text>
                                                                        <v-form @submit.prevent>
                                                                            <v-row>
                                                                                <v-col>
                                                                                    <v-text-field v-model="formCommodity.name"
                                                                                                  label="Name"
                                                                                                  variant="outlined"
                                                                                    ></v-text-field>
                                                                                </v-col>
                                                                            </v-row>
                                                                        </v-form>
                                                                    </v-card-text>
                                                                    <v-card-actions>
                                                                        <v-btn text="store"
                                                                               @click="storeCommodity"
                                                                               variant="tonal"
                                                                               color="orange"
                                                                        ></v-btn>
                                                                    </v-card-actions>
                                                                </v-card>
                                                            </template>
                                                        </v-dialog>
                                                    </v-col>
                                                </v-row>
                                                <v-row>
                                                    <v-col>
                                                        <v-data-table :items="commodities"
                                                                      items-per-page="100"
                                                                      :headers="headerCommodities"
                                                                      fixed-header
                                                                      height="779px"
                                                                      density="compact"
                                                                      hover
                                                                      class="border rounded"
                                                        ></v-data-table>
                                                    </v-col>
                                                </v-row>
                                            </v-container>
                                        </v-tabs-window-item>
                                    </v-tabs-window>
                                </v-tabs-window-item>
                                <!--  E N D  P R O D U C T S  -->







                                <!--                        З А К У П К А                  -->
                                <v-tabs-window-item value="purchases">
                                    <v-row>
                                        <v-col></v-col>
                                        <v-col></v-col>
                                        <v-col lg="2">
                                            <v-btn text="+ check"
                                                   @click="dialogFormCheck = !dialogFormCheck"
                                                   variant="tonal"
                                                   density="compact"
                                                   color="deep-orange"
                                            ></v-btn>
                                            <v-dialog v-model="dialogFormCheck"
                                                      width="1001"
                                            >
                                                <v-card>
                                                    <v-card-title>Form Check</v-card-title>
                                                    <v-card-text>
                                                        <v-form @submit.prevent>
                                                            <v-row>
                                                                <v-col lg="4">
                                                                    <input type="date"
                                                                           v-model="formCheck.date"></input>
                                                                </v-col>
                                                                <v-col lg="8">
                                                                    <v-autocomplete :items="entities"
                                                                                    :item-value="'id'"
                                                                                    :item-title="'name'"
                                                                                    v-model="formCheck.entity_id"
                                                                                    variant="solo"
                                                                                    density="comfortable"></v-autocomplete>
                                                                </v-col>
                                                            </v-row>
                                                            <v-row>
                                                                <v-col lg="4">
                                                                    <v-text-field v-model="formCheck.amount"
                                                                                  label="Amount"
                                                                                  variant="underlined"
                                                                                  density="comfortable"
                                                                                  color="deep-orange"></v-text-field>
                                                                </v-col>
                                                            </v-row>
                                                        </v-form>
                                                    </v-card-text>
                                                    <v-card-actions>
                                                        <v-divider vertical
                                                                   thickness="1"
                                                                   opacity="0.8"></v-divider>
                                                        <v-btn text="store"
                                                               @click="storeCheck"
                                                               variant="tonal"
                                                               density="compact"
                                                               color="blue-grey"></v-btn>
                                                    </v-card-actions>
                                                </v-card>
                                            </v-dialog>
                                        </v-col>
                                    </v-row>
                                    <v-row>
                                        <v-col>
                                            <v-list variant="tonal"
                                                    density="compact"
                                            >
                                                <v-list-item v-for="check in checks"
                                                             class="hover:text-orange-600 hover:bg-zinc-700"
                                                >
                                                    <Link :href="route('checks.show', check.id)">
                                                        <v-row>
                                                            <v-col>
                                                                {{check.date}}
                                                            </v-col>
                                                            <v-col lg="6">
                                                                <div>{{check.entity.name}}</div>
                                                                <div>{{check.entity.classification.name}}</div>
                                                            </v-col>
                                                            <v-col>
                                                                {{check.amount}}
                                                            </v-col>
                                                        </v-row>
                                                    </Link>
                                                </v-list-item>
                                            </v-list>
                                        </v-col>
                                        <v-col>
                                            <v-list-item v-for="purchase in purchases.slice().sort((a, b) => new Date(b.date) - new Date(a.date))"
                                                         :key="purchase.id"
                                                         class="text-[11px] hover:text-orange-600 hover:bg-zinc-700"
                                            >
                                                <v-row>
                                                    <v-col>
                                                        <span>{{ date.format(purchase.date, 'fullDate') }}</span>
                                                    </v-col>
                                                    <v-col>
                                                        <span>{{ purchase.amount }}</span>
                                                    </v-col>
                                                    <v-col>
                                                        <span>{{ purchase.entity.name }}</span>
                                                    </v-col>
                                                </v-row>
                                            </v-list-item>
                                        </v-col>
                                    </v-row>
                                </v-tabs-window-item>
                                <!--           К О Н Е Ц  З А К У П К А          -->






                                <!--        К Л А С С И Ф И К А Т О Р Ы        -->
                                <v-tabs-window-item value="segments">
                                    <v-container fluid>
                                        <v-tabs v-model="tabsSegments">
                                            <v-tab value="catalogs">Catalogs</v-tab>
                                            <v-tab value="fields">Fields</v-tab>
                                            <v-tab value="segments_tab">Segments</v-tab>
                                        </v-tabs>
                                        <v-tabs-window v-model="tabsSegments">
                                            <v-tabs-window-item value="catalogs">
                                                <v-container fluid>
                                                    <v-row>
                                                        <v-col>
                                                            <v-data-table :items="catalogs"
                                                                          items-per-page="25"
                                                                          :headers="headersCatalogs"
                                                                          density="compact"
                                                                          hover
                                                                          class="border rounded"
                                                            >
                                                                <template v-slot:item.uri="{item}">
                                                                    <a :href="item.uri" target="_blank">
                                                                        {{item.uri}}
                                                                    </a>
                                                                </template>
                                                            </v-data-table>
                                                        </v-col>
                                                    </v-row>
                                                </v-container>
                                            </v-tabs-window-item>
                                            <v-tabs-window-item value="fields">
                                                <v-container fluid>
                                                    <v-row>
                                                        <v-col cols="2">
                                                            <v-text-field label="Search"
                                                                          variant="solo-inverted"
                                                                          density="compact"
                                                                          hide-details
                                                            ></v-text-field>
                                                        </v-col>
                                                    </v-row>
                                                    <v-row>
                                                        <v-col>
                                                            <v-data-table :items="fields"
                                                                          items-per-page="100"
                                                                          :headers="headerFields"
                                                                          fixed-header
                                                                          height="500px"
                                                                          density="compact"
                                                                          hover
                                                                          class="border rounded"
                                                            ></v-data-table>
                                                        </v-col>
                                                    </v-row>
                                                </v-container>
                                            </v-tabs-window-item>
                                            <v-tabs-window-item value="segments_tab">
                                                <v-container fluid>
                                                    <v-row>
                                                        <v-col>
                                                            <v-data-table :items="segments"
                                                            ></v-data-table>
                                                        </v-col>
                                                    </v-row>
                                                </v-container>
                                            </v-tabs-window-item>
                                        </v-tabs-window>
                                    </v-container>
                                </v-tabs-window-item>
                                <!--           К О Н Е Ц  К Л А С С И Ф И К А Т О Р Ы           -->







                                <!--           К О Н Т А К Т Ы           -->
                                <v-tabs-window-item value="contacts">
                                    <v-row>
                                        <v-col>
                                            <v-tabs v-model="tabsContacts">
                                                <v-tab value="telephones">Телефоны</v-tab>
                                                <v-tab value="uris">Uris</v-tab>
                                            </v-tabs>
                                            <v-tabs-window v-model="tabsContacts">
                                                <v-tabs-window-item value="telephones">
                                                    <v-container>
                                                        <v-row>
                                                            <v-col lg="3">
                                                                <v-text-field v-model="searchTelephones"
                                                                              label="Поиск по телефонам"
                                                                              variant="solo"
                                                                              density="comfortable"
                                                                              hide-details
                                                                ></v-text-field>
                                                            </v-col>
                                                            <v-col lg="1">
                                                                <v-btn text="New tel"
                                                                       @click="dialogFormTelephone = !dialogFormTelephone"
                                                                       variant="elevated"
                                                                       density="comfortable"
                                                                       color="indigo-lighten-1"
                                                                ></v-btn>
                                                                <v-dialog v-model="dialogFormTelephone"
                                                                          width="500"
                                                                >
                                                                    <template v-slot:default="{ isActive }">
                                                                        <v-card>
                                                                            <v-card-title>Form Telephone</v-card-title>
                                                                            <v-card-text>
                                                                                <v-form @submit.prevent>
                                                                                    <v-row>
                                                                                        <v-col>
                                                                                            <v-text-field v-model="formTelephone.number"
                                                                                                          label="Number"
                                                                                                          placeholder="+...."
                                                                                                          variant="outlined"
                                                                                                          color="indigo"
                                                                                            ></v-text-field>
                                                                                        </v-col>
                                                                                    </v-row>
                                                                                </v-form>
                                                                            </v-card-text>
                                                                            <v-card-actions>
                                                                                <v-divider vertical
                                                                                           thickness="1"
                                                                                           opacity="90"
                                                                                ></v-divider>

                                                                                <v-btn @click="storeTelephone"
                                                                                       text="save"
                                                                                       variant="elevated"
                                                                                       color="success"
                                                                                ></v-btn>
                                                                            </v-card-actions>
                                                                        </v-card>
                                                                    </template>
                                                                </v-dialog>
                                                            </v-col>
                                                        </v-row>
                                                        <v-row>
                                                            <v-col>
                                                                <v-data-table :items="filteredTelephones"
                                                                              items-per-page="100"
                                                                              :headers="headerTelephones"
                                                                              fixed-header
                                                                              height="500px"
                                                                              density="compact"
                                                                              hover
                                                                >
                                                                    <template v-slot:item.entities="{item}">
                                                                        <div v-for="entity in item.entities"
                                                                             class="text-xs"
                                                                        >{{entity.name}}</div>
                                                                    </template>
                                                                </v-data-table>
                                                            </v-col>
                                                        </v-row>
                                                    </v-container>
                                                </v-tabs-window-item>
                                                <v-tabs-window-item value="uris">
                                                    <v-row>
                                                        <v-col>
                                                            <v-data-table :items="uris"
                                                                          :headers="headerUris"
                                                                          items-per-page="1000"
                                                                          density="compact"
                                                                          class="text-xs"
                                                                          hover
                                                            >
                                                                <template v-slot:item.address="{item}">
                                                                    <a :href="item.address" target="_blank"
                                                                       class="text-xs text-green-400 inline-block"
                                                                    >{{item.address}}</a>
                                                                </template>
                                                                <template v-slot:item.owners="{item}">
                                                                    <div v-for="unit in item.owners">
                                                                        <Link :href="route('web.unit.show', unit.id)">{{unit.name}}</Link>
                                                                    </div>
                                                                </template>
                                                                <template v-slot:item.created_at="{item}">
                                                                    <span class="text-xs font-sans">{{date.format(item.created_at, 'fullDate')}}</span>
                                                                </template>
                                                            </v-data-table>
                                                        </v-col>
                                                    </v-row>
                                                </v-tabs-window-item>
                                            </v-tabs-window>
                                        </v-col>
                                    </v-row>
                                </v-tabs-window-item>
                                <!--        К О Н Е Ц  К О Н Т А К Т Ы         -->













                                <!--                                M A I L (не работает)!!!-->
                                <v-tabs-window-item value="eleven">
                                    <div class="max-w-md mx-auto mt-10 p-6 bg-white rounded-lg shadow-md border border-gray-200">
                                        <h1 class="text-2xl font-bold text-center text-gray-800 mb-4">📧 Отправить письмо</h1>
                                        <form @submit.prevent="sendMail" class="space-y-4">
                                            <div>
                                                <label for="email" class="block text-sm font-medium text-gray-700">Email:</label>
                                                <input
                                                    type="email"
                                                    v-model="email"
                                                    id="email"
                                                    placeholder="Введите ваш email"
                                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                    required
                                                />
                                            </div>
                                            <v-spacer></v-spacer>
                                            <div>
                                                <label for="message" class="block text-sm font-medium text-gray-700">Сообщение:</label>
                                                <textarea
                                                    v-model="message"
                                                    id="message"
                                                    placeholder="Введите ваше сообщение"
                                                    rows="4"
                                                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                                    required
                                                ></textarea>
                                            </div>
                                            <button
                                                type="submit"
                                                class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                            >
                                                Отправить
                                            </button>
                                        </form>
                                        <p v-if="successMessage" class="mt-4 text-green-600 text-center">{{ successMessage }}</p>
                                    </div>
                                </v-tabs-window-item>
                                <!--  E N D  M A I L    -->












                                <!--           B R A N D S               -->
                                <v-tabs-window-item value="brands">
                                    <v-sheet>
                                        <v-text-field v-model="searchBrands"
                                                      label="Поиск по брендам"
                                                      variant="solo"
                                                      density="compact"
                                                      color="deep-purple"
                                        ></v-text-field>
                                        <div v-for="brand in filteredBrands"
                                             class="text-xs"
                                        >{{brand.name}}</div>
                                    </v-sheet>
                                </v-tabs-window-item>
                                <!--              E N D  B R A N D S               -->




                                <!--      C O N P O N E N T S       -->
                                <v-tabs-window-item value="components">
                                    <v-container>
                                        <v-row>
                                            <v-col cols="8">

                                            </v-col>
                                            <v-col cols="4">
                                                <v-dialog transition="dialog-top-transition"
                                                          width="605"
                                                >
                                                    <template v-slot:activator="{ props: activatorProps }">
                                                        <v-btn v-bind="activatorProps"
                                                               text="Добавить"
                                                               block
                                                               variant="elevated"
                                                               color="purple"
                                                        ></v-btn>
                                                    </template>
                                                    <template v-slot:default="{ isActive }">
                                                        <v-card>
                                                            <v-card-title>Form Component</v-card-title>
                                                            <v-card-text>
                                                                <v-form @submit.prevent>
                                                                    <v-row>
                                                                        <v-text-field v-model="formComponent.name"
                                                                                      label="Name"
                                                                                      variant="outlined"
                                                                        ></v-text-field>
                                                                    </v-row>
                                                                    <v-row>
                                                                        <v-row>
                                                                            <v-col></v-col>
                                                                            <v-col>
                                                                                <v-btn text="save"
                                                                                       variant="elevated"
                                                                                       @click="storeComponent"
                                                                                       color="purple-darken-4"
                                                                                ></v-btn>
                                                                            </v-col>
                                                                            <v-col></v-col>
                                                                        </v-row>
                                                                    </v-row>
                                                                </v-form>
                                                            </v-card-text>
                                                        </v-card>
                                                    </template>
                                                </v-dialog>
                                            </v-col>
                                        </v-row>
                                    </v-container>
                                </v-tabs-window-item>
                                <!--      E N D  C O M P O N E N T S      -->
                            </v-tabs-window>
                        </v-card-text>
                    </v-card>
                </v-col>
            </v-row>
        </v-container>
    </v-theme-provider>
</template>

<style scoped>
.rounded-full {
    border-radius: 50%;
}
</style>
