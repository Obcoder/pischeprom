import { readdir, readFile } from 'node:fs/promises'

const assetDirectory = new URL('../public/build/assets/', import.meta.url)
const cssFiles = (await readdir(assetDirectory)).filter((file) => file.endsWith('.css'))

let mdiStylesheet = null

for (const file of cssFiles) {
    const stylesheet = await readFile(new URL(file, assetDirectory), 'utf8')

    if (stylesheet.includes('.mdi-ab-testing:before')) {
        mdiStylesheet = stylesheet
        break
    }
}

if (mdiStylesheet === null) {
    throw new Error('The generated Material Design Icons stylesheet was not found.')
}

const rawPrivateUseCodePoint = /[\uE000-\uF8FF\u{F0000}-\u{FFFFD}\u{100000}-\u{10FFFD}]/u

if (rawPrivateUseCodePoint.test(mdiStylesheet)) {
    throw new Error('Generated icon CSS contains raw private-use Unicode code points.')
}

if (!mdiStylesheet.toLowerCase().includes('.mdi-ab-testing:before{content:"\\f01c9"}')) {
    throw new Error('Generated icon CSS does not contain the expected escaped MDI code point.')
}

console.log('icon_css_encoding=PASS')
