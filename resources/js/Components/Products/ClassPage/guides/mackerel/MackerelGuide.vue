<script setup>
import InlineGoodLink from '../../InlineGoodLink.vue'
import CaliberDiagram from './CaliberDiagram.vue'
import GlazeDiagram from './GlazeDiagram.vue'
import MackerelFaq from './MackerelFaq.vue'
defineProps({ inlineGoods: { type: Object, default: () => ({}) } })
</script>

<template>
<section id="guide" class="section guide-section">
  <div class="wrap">
    <div class="section-heading"><div><div class="eyebrow">02 / КОРОТКО О ГЛАВНОМ</div><h2>Размер — ещё не всё.</h2></div><p>Сравнивайте партии<br>по четырём характеристикам.</p></div>
    <div class="guide-grid">
      <article class="guide-item"><span class="guide-number">01</span><h3>Вид и происхождение</h3><p>Атлантическая и японская скумбрия — разные виды. Страна поставки не заменяет район вылова и латинское название в документах.</p><span class="micro-label"><i>Scomber scombrus</i> · <i>S. japonicus</i></span></article>
      <article class="guide-item"><span class="guide-number">02</span><h3>Калибр</h3><p>400–600 — диапазон массы одной рыбы. 500+ и 600+ задают нижнюю границу, а не средний вес и не процент жира.</p><a class="inline-link" href="#caliber">Расшифровать размер</a></article>
      <article class="guide-item"><span class="guide-number">03</span><h3>Разделка</h3><p>Целая рыба, потрошёная тушка и филе дают разный выход. Сравнивайте стоимость пригодного продукта с учётом потерь и работы.</p><span class="micro-label">Цена сырья ≠ себестоимость филе</span></article>
      <article class="guide-item"><span class="guide-number">04</span><h3>Жирность и сохранность</h3><p>Жирность зависит от сезона и партии. Крупный размер сам по себе её не гарантирует. Для производства нужны фактические показатели.</p><a class="inline-link" href="#quality">Что проверить при приёмке</a></article>
    </div>
    <CaliberDiagram />
    <p class="editorial-line">Нужна крупная целая рыба? Посмотрите <InlineGoodLink :good-id="75" :goods="inlineGoods">скумбрию 600+ с Фарерских островов</InlineGoodLink>. Фактическую жирность и выход оценивайте по партии.</p>
  </div>
</section>

<section id="uses" class="section wrap">
  <div class="section-heading"><div><div class="eyebrow">03 / ОТ СЫРЬЯ К ПРОДУКТУ</div><h2>Выбирайте под свою задачу.</h2></div></div>
  <div class="uses-layout">
    <figure class="uses-photo"><img src="/class-assets/mackerel/mackerel-smoked.jpg" alt="Копчёная скумбрия с салатом — один из вариантов приготовления" width="1100" height="825" loading="lazy"><figcaption>Насыщенный вкус.<br><i>Много возможностей.</i><span>Пример приготовления</span></figcaption></figure>
    <div class="uses-content">
      <div class="use-row"><span>01</span><div><h3>Магазин и рыбная лавка</h3><p>Однородный калибр, целое брюшко, чистая поверхность и понятная маркировка. Для продажи целой рыбой — <InlineGoodLink :good-id="104" :goods="inlineGoods">ску́мбрия 500+ из Перу</InlineGoodLink>.</p></div></div>
      <div class="use-row"><span>02</span><div><h3>Кухня и HoReCa</h3><p>Удобный размер порции, выход после разделки, сочность и целостность кожи. Подходит для запекания, гриля и горячих блюд.</p></div></div>
      <div class="use-row"><span>03</span><div><h3>Посол и копчение</h3><p>Проверяйте жирность, размерную однородность и прочность тканей. Слабый посол и холодное копчение не заменяют обезвреживание паразитов.</p></div></div>
      <div class="use-row"><span>04</span><div><h3>Консервы и переработка</h3><p>Важны выход, зачистка и поведение мяса при нагревании. Проведите пробную разделку и технологическую выработку.</p></div></div>
    </div>
  </div>
</section>

<section id="glaze" class="section mass-section">
  <div class="wrap mass-grid">
    <div class="mass-copy"><div class="eyebrow light">04 / ЭКОНОМИКА ЗАКУПКИ</div><h2>Считайте рыбу.<br><em>Учитывайте лёд.</em></h2><p>Глазурь защищает поверхность от подсыхания и окисления. Её масса и потери сока после размораживания — разные показатели.</p><p>Если на этикетке уже указано «нетто без глазури», повторно вычитать процент льда не нужно. <a class="source-ref" href="#source-reg" aria-label="Источник: ТР ЕАЭС 040/2016">[1]</a></p><div class="mass-reminder"><span aria-hidden="true">≠</span><span>Масса коробки<br><b>Масса рыбы без глазури</b></span></div></div>
    <GlazeDiagram />
  </div>
</section>

<section id="quality" class="section wrap">
  <div class="section-heading"><div><div class="eyebrow">05 / КАЧЕСТВО И ХОЛОДОВАЯ ЦЕПЬ</div><h2>Смотрите глубже упаковки.</h2></div><a class="text-link" href="#passport">Паспорт партии</a></div>
  <div class="quality-layout">
    <div class="quality-table"><div class="quality-table-head"><span>Что проверить</span><span>На что обратить внимание</span></div><div><b>Поверхность и брюшко</b><p>Целостность, отсутствие загрязнения и глубоких сухих участков. Повреждения оценивают с учётом причины.</p></div><div><b>Запах после размораживания</b><p>Без кислого, прогорклого, аммиачного или постороннего запаха.</p></div><div><b>Структура мяса</b><p>Без выраженного распада и кашеобразности. Потери жидкости измеряют отдельно.</p></div><div><b>Упаковка и маркировка</b><p>Целая упаковка; читаемые вид, разделка, масса, дата, партия и условия хранения.</p></div></div>
    <aside class="cold-card"><span class="eyebrow">МОРОЖЕНАЯ РЫБА</span><strong>−18<span>°C</span></strong><h3>и ниже</h3><p>Соблюдайте более строгий режим, если он указан изготовителем. Срок годности — по маркировке конкретной партии. <a class="source-ref" href="#source-reg">[1]</a></p><div class="cold-divider"></div><b>Размораживайте в холодильнике.</b><p>Избегайте длительного нагрева при комнатной температуре. Собирайте стекающую жидкость. <a class="source-ref" href="#source-fda">[2]</a></p></aside>
  </div>
  <div class="safety-note"><span class="note-symbol" aria-hidden="true">!</span><div><h3>Свежий вид не подтверждает всю безопасность</h3><p>Гистамин нельзя надёжно оценить по запаху; уже накопившийся гистамин не устраняется замораживанием или обычным приготовлением. Для скумбрии особенно важны время, температура и контроль партии. <a class="source-ref" href="#source-histamine">[3]</a></p></div></div>
</section>

<MackerelFaq :inline-goods="inlineGoods" />
</template>
