const fs = require('fs');
const path = require('path');

const dir = 'c:\\xampp\\htdocs\\FlowStack\\frontend';
const files = fs.readdirSync(dir).filter(f => f.endsWith('.html'));

const emojiMap = {
    '✅': '<i data-lucide="check-circle" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '⏱': '<i data-lucide="timer" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '◆': '<i data-lucide="git-merge" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '⭐': '<i data-lucide="award" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '⚖': '<i data-lucide="scale" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '▶': '<i data-lucide="compass" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '📊': '<i data-lucide="bar-chart" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '🧠': '<i data-lucide="brain" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '🎯': '<i data-lucide="target" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '🔥': '<i data-lucide="flame" style="width:18px;height:18px;display:inline-block;vertical-align:middle;"></i>',
    '👋': '' // Hand emoji in 'Good morning' doesn't need a Lucide icon usually, or just remove it.
};

files.forEach(f => {
    let content = fs.readFileSync(path.join(dir, f), 'utf8');
    let changed = false;
    for (let emoji in emojiMap) {
        if (content.includes(emoji)) {
            content = content.split(emoji).join(emojiMap[emoji]);
            changed = true;
        }
    }
    if (changed) {
        fs.writeFileSync(path.join(dir, f), content);
        console.log('Updated ' + f);
    }
});
