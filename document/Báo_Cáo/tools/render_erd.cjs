const fs=require('fs'),path=require('path');
const {instance}=require(process.env.TEMP+'/clinic-report-tools/node_modules/@viz-js/viz');
const base=path.resolve(__dirname,'..');
const schema=JSON.parse(fs.readFileSync(path.join(__dirname,'schema.json'),'utf8'));
const groups=JSON.parse(fs.readFileSync(path.join(__dirname,'erd-groups.json'),'utf8'));
(async()=>{
const viz=await instance();
for(const group of groups){
 let dot='digraph ERD {\ngraph [rankdir=LR, nodesep=.35, ranksep=.48, pad=.12, splines=spline, outputorder=edgesfirst, bgcolor="white"];\nnode [shape=plain,fontname="Times New Roman"];\nedge [color="#49635f",penwidth=.8,arrowsize=.65];\n';
 const edges=[];
 for(const name of group.tables){
  const table=schema[name];
  const relevant=table.foreign_keys.filter(f=>group.tables.includes(f.table)&&!['created_by','updated_by','paid_by','published_by','granted_by','processed_by','signed_by','verified_by'].includes(f.from));
  const visible=table.columns.filter(c=>c.pk||relevant.some(f=>f.from===c.name)||['status','work_date'].includes(c.name));
  const header=name.replaceAll('_','_<WBR/>');
  // Keep identifier text intact; Graphviz HTML does not support WBR.
  let label=`<TABLE BORDER="1" CELLBORDER="0" CELLSPACING="0" CELLPADDING="4" COLOR="#47756e"><TR><TD BGCOLOR="#e9f3f2"><FONT FACE="Times New Roman" POINT-SIZE="14"><B>${name}</B></FONT></TD></TR><HR/>`;
  for(const c of visible){
   const mark=c.pk?'PK':relevant.some(f=>f.from===c.name)?'FK':'';
   label+=`<TR><TD PORT="${c.name}" ALIGN="LEFT"><FONT FACE="Times New Roman" POINT-SIZE="12">${mark?mark+'  ':''}${c.name}</FONT></TD></TR>`;
  }
  dot+=`"${name}" [label=<${label}</TABLE>>];\n`;
  for(const fk of relevant)edges.push(`"${name}":"${fk.from}":e -> "${fk.table}":"${fk.to}":w;`);
 }
 dot+=edges.join('\n')+'\n}\n';
 const prefix=path.join(base,'figures/diagrams',group.key);
 fs.writeFileSync(prefix+'.dot',dot);
 fs.writeFileSync(prefix+'.svg',viz.renderString(dot,{format:'svg',engine:'dot'}));
 console.log(group.key);
}
})().catch(e=>{console.error(e);process.exit(1)});
