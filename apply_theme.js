const fs = require('fs');
const cssPath = 'c:\\xampp\\htdocs\\FlowStack\\frontend\\assets\\css\\app.css';
let css = fs.readFileSync(cssPath, 'utf8');

if (!css.includes('data-theme="light"')) {
    // 1. Add new variables to :root
    css = css.replace('--tc: 0.4s cubic-bezier(.4, 0, .2, 1);\n}', '--tc: 0.4s cubic-bezier(.4, 0, .2, 1);\n  --glass: rgba(30, 41, 59, 0.4);\n  --glass-heavy: rgba(11, 15, 25, 0.6);\n  --glow-op: 0.2;\n}');
    
    // 2. Add html[data-theme="light"]
    const lightTheme = `
html[data-theme="light"] {
  --p: #6366F1; --p-dark: #4F46E5; --p-xdark: #3730A3; --p-raw: 99,102,241;
  --p-light: rgba(99, 102, 241, 0.1);
  --bg: #F1F5F9;
  --surface: rgba(255, 255, 255, 0.7);
  --surface2: rgba(255, 255, 255, 0.9);
  --border: rgba(0, 0, 0, 0.08);
  --border2: rgba(0, 0, 0, 0.15);
  --tx: #0F172A; --tx2: #475569; --tx3: #94A3B8;
  --s1: 0 4px 15px rgba(0, 0, 0, 0.05);
  --s2: 0 10px 25px rgba(0, 0, 0, 0.08);
  --s3: 0 20px 40px rgba(0, 0, 0, 0.12);
  --s-primary: 0 0 15px rgba(99, 102, 241, 0.3);
  --glass: rgba(255, 255, 255, 0.6);
  --glass-heavy: rgba(255, 255, 255, 0.9);
  --glow-op: 0.08;
}
`;
    css = css.replace('/* ── Reset', lightTheme + '\n/* ── Reset');
    
    // 3. Replace hardcoded alphas with variables
    css = css.split('rgba(11, 15, 25, 0.6)').join('var(--glass-heavy)');
    css = css.split('rgba(30, 41, 59, 0.4)').join('var(--glass)');
    css = css.split('opacity: 0.2;').join('opacity: var(--glow-op);');
    
    fs.writeFileSync(cssPath, css);
    console.log('Light theme successfully injected into app.css');
} else {
    console.log('Light theme already exists');
}
