'use strict';
const assert=require('node:assert/strict');const fs=require('node:fs');const path=require('node:path');
const root=path.resolve(__dirname,'../..');const sourceRoot=path.join(root,'files/client/custom/modules/document-builder/src');const cache=new Map();let active;
function define(dependencies,factory){active={dependencies,factory};}
function load(name){if(cache.has(name))return cache.get(name);active=null;const file=path.join(sourceRoot,`${name.replace(/^document-builder:/,'')}.js`);new Function('define',fs.readFileSync(file,'utf8'))(define);const value=active.factory(...active.dependencies.map(load));cache.set(name,value);return value;}
const BrowserRenderer=load('document-builder:editor/renderer/browser-renderer');const PageGeometry=load('document-builder:editor/geometry/page-geometry');const StyleResolver=load('document-builder:editor/style/style-resolver');
const layout=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/layout/phase-08-default.json')));const box=()=>Object.fromEntries(['top','right','bottom','left'].map(edge=>[edge,{value:0,unit:'mm'}]));
const identity={source:'entity',type:'direct',entityType:'Contact',path:['name']};const condition={target:'element',mode:'all',rules:[{identity,valueType:'text',operator:'equals',operand:'Ana'}]};
layout.capabilities=['layout.flow'];layout.sections=[{id:'section',type:'flow-section',children:[{id:'heading',type:'heading',content:[{type:'text',text:'Title',marks:[]}],level:2,keepWithNext:true,condition}],margin:box(),padding:box(),minHeight:{value:20,unit:'mm'},keepTogether:false,startNewPage:false}];
const renderer=new BrowserRenderer({pageGeometry:new PageGeometry(),styleResolver:new StyleResolver(['DejaVu Sans'])});const values=new Map([[JSON.stringify(identity),{state:'present',type:'text',value:'Other'}]]);
let result=renderer.render(layout,{previewValues:values,evaluateConditions:true});assert.equal(result.rows.some(row=>row.id==='heading'),false);assert.equal(result.rows.some(row=>row.id==='section'),true);
layout.sections[0].children[0].condition.target='parent';result=renderer.render(layout,{previewValues:values,evaluateConditions:true});assert.equal(result.rows.length,0);
result=renderer.render(layout,{previewValues:values,evaluateConditions:false});assert.equal(result.rows.length,2,'Editor mode must keep conditional nodes editable.');
console.log('Phase 31 browser-preview condition target tests passed.');
