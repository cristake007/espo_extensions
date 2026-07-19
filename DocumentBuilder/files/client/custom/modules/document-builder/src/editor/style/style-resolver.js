define(['document-builder:editor/state/node-tree'], NodeTree => {
    const ENUMS = Object.freeze({
        fontWeight: ['normal','bold','100','200','300','400','500','600','700','800','900'],
        fontStyle: ['normal','italic'], textDecoration: ['none','underline'],
        textTransform: ['none','uppercase','lowercase','capitalize'],
        horizontalAlignment: ['start','center','end','stretch'], verticalAlignment: ['start','center','end'],
    });
    const color = value => /^#[0-9A-Fa-f]{6}$/.test(value || '') ? value : null;
    const measure = (value, units, minimum, maximum) => value && units.includes(value.unit) &&
        Number.isFinite(value.value) && value.value >= minimum && value.value <= maximum ? value : null;

    return class StyleResolver {
        constructor(allowedFonts = ['DejaVu Sans']) { this.allowedFonts = allowedFonts; }
        resolve(layout, nodeId) {
            const defaults = layout.document.defaults;
            const result = {fontFamily: defaults.fontFamily, fontSize: defaults.fontSize,
                color: defaults.color, lineHeight: defaults.lineHeight, ...(layout.document.style || {})};
            const index = NodeTree.index(layout); const layers = []; let location = index.get(nodeId);
            while (location) { layers.unshift(location.node.style || {}); location = location.parentId ? index.get(location.parentId) : null; }
            layers.forEach(layer => Object.assign(result, layer));
            if (!this.allowedFonts.includes(result.fontFamily)) result.fontFamily = this.allowedFonts[0] || 'DejaVu Sans';
            return result;
        }
        toCss(style, mmToPx) {
            const css = [];
            const add = (name, value) => css.push(`${name}: ${value}`);
            if (this.allowedFonts.includes(style.fontFamily)) add('font-family', `'${style.fontFamily.replace(/'/g, '')}'`);
            const fontSize = measure(style.fontSize, ['pt'], 1, 512); if (fontSize) add('font-size', `${fontSize.value}pt`);
            const textColor = color(style.color); if (textColor) add('color', textColor);
            const background = color(style.backgroundColor); if (background) add('background-color', background);
            if (Number.isFinite(style.opacity) && style.opacity >= 0 && style.opacity <= 1) add('opacity', style.opacity);
            Object.entries(ENUMS).forEach(([key, values]) => {
                if (!values.includes(style[key])) return;
                const properties = {fontWeight:'font-weight',fontStyle:'font-style',textDecoration:'text-decoration',textTransform:'text-transform',horizontalAlignment:'text-align',verticalAlignment:'vertical-align'};
                const mappings = {start:'left',end:'right',stretch:'justify'};
                add(properties[key], mappings[style[key]] || style[key]);
            });
            if (Number.isFinite(style.lineHeight) && style.lineHeight >= .5 && style.lineHeight <= 5) add('line-height', style.lineHeight);
            const spacing = measure(style.letterSpacing, ['pt'], -20, 100); if (spacing) add('letter-spacing', `${spacing.value}pt`);
            ['width','height'].forEach(key => { const value=measure(style[key],['mm','percent'],0,style[key]?.unit==='percent'?100:2000); if(value)add(key,value.unit==='mm'?`${mmToPx(value.value)}px`:`${value.value}%`); });
            ['margin','padding'].forEach(key => { const box=style[key]; if (!box) return; const values=['top','right','bottom','left'].map(edge=>measure(box[edge],['mm'],0,2000)); if(values.every(Boolean)) add(key,values.map(value=>`${mmToPx(value.value)}px`).join(' ')); });
            const border=style.border; const borderWidth=measure(border?.width,['pt'],0,512); const borderColor=color(border?.color); if(borderWidth&&borderColor&&['none','solid','dashed','dotted','double'].includes(border.style)) add('border',`${borderWidth.value}pt ${border.style} ${borderColor}`);
            return css.join('; ');
        }
    };
});
