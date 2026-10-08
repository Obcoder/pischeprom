import MackerelGuide from './guides/mackerel/MackerelGuide.vue'

export const classGuides = {
    mackerel: {
        article: MackerelGuide,
        catalogTitle: 'Скумбрия в каталоге',
        catalogAction: 'Каталог скумбрии',
        hero: {
            subtitle: ['Знакомый вкус.', 'Важные детали.'],
            description: ['От калибра до качества партии.', 'Помогаем разобраться и выбрать рыбу', 'для вашей кухни или бизнеса.'],
            action: 'Выбрать скумбрию',
            image: '/class-assets/mackerel/mackerel-hero.jpg',
            imageAlt: 'Атлантическая скумбрия: серебристые бока и характерные тёмные полосы на спине',
            imageWidth: 1500,
            imageHeight: 1051,
            caption: 'Атлантическая скумбрия',
            scientificName: 'Scomber scombrus',
        },
        navigation: [
            { id: 'catalog', title: 'Каталог' }, { id: 'guide', title: 'Как выбрать' },
            { id: 'uses', title: 'Применение' }, { id: 'glaze', title: 'Глазурь и выход' },
            { id: 'quality', title: 'Качество' }, { id: 'faq', title: 'Вопросы' },
        ],
        contactTitle: ['Расскажите,', 'какая скумбрия вам нужна.'],
        verifiedOn: '8 октября 2026',
        sources: [
            { id: 'source-reg', title: 'ТР ЕАЭС 040/2016 «О безопасности рыбы и рыбной продукции»', url: 'https://mosrst.ru/wp-content/uploads/2020/02/tr-eaes-040-2016.pdf', description: 'глазурь, масса, температурные требования; опубликованный текст.' },
            { id: 'source-fda', title: 'FDA: Selecting and Serving Fresh and Frozen Seafood Safely', url: 'https://www.fda.gov/food/buy-store-serve-safe-food/selecting-and-serving-fresh-and-frozen-seafood-safely', description: 'хранение и размораживание.' },
            { id: 'source-histamine', title: 'FDA: Scombrotoxin (Histamine) Formation', url: 'https://www.fda.gov/media/80248/download', description: 'контроль гистамина.' },
            { id: 'source-fao', title: 'FAO: Yield and nutritional value', url: 'https://www.fao.org/4/t0219e/t0219e03.htm', description: 'состав и выход съедобной части.' },
            { id: 'source-gost', title: 'Росстандарт: ГОСТ 35273-2025', url: 'https://protect.gost.ru/gost/details/b6459d83-de78-4536-b041-dcece2ddd094', description: 'статус и дата введения.' },
        ],
        photos: [
            { title: 'Атлантическая скумбрия', author: 'Petar Milošević', source: 'https://commons.wikimedia.org/wiki/File:Atlantic_mackerel_(Scomber_scombrus).jpg', license: 'CC BY-SA 4.0', licenseUrl: 'https://creativecommons.org/licenses/by-sa/4.0/' },
            { title: 'Копчёная скумбрия', author: 'Jocian', source: 'https://commons.wikimedia.org/wiki/File:Smoked_mackerel-01.jpg', license: 'CC BY-SA 3.0', licenseUrl: 'https://creativecommons.org/licenses/by-sa/3.0/' },
        ],
    },
}

export function resolveClassGuide(key) {
    return Object.hasOwn(classGuides, key) ? classGuides[key] : null
}
