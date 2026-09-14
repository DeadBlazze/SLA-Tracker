let layout = `
    <div class="sla_container">
        <div class="sla_header">
            <p>SLA Tracker</p>
        </div>
        <div class="sla_main">
            <ul>
                <li>Настройки</li>
                <li>Выгрузка</li>
                <li>Просрочки<span id="delayCol">128</span></li>
            </ul>
        </div>
        <div class="sla_counter">
            <span class="sla_counter-display"></span>
            <button id="sla_counterButton">+</button>
        </div>
    </div>`;

const root = document.getElementById('sla-widget-root')
if (root) {
    root.insertAdjacentHTML('afterbegin', layout);
}
const delayCol = document.getElementById('delayCol')
let count = 256;
delayCol.innerText = count

const counterButton = document.getElementById('sla_counterButton')
counterButton.addEventListener('click', () => {
    count++
    delayCol.innerText = count;
});