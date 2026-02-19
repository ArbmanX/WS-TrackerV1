import { writeFileSync } from 'fs';

// Theme: Just Black Neon (from tmux config)
const theme = {
  process:  { bg: '#0a0a12', stroke: '#00ffff', text: '#00ffff' },
  store:    { bg: '#0a0a12', stroke: '#39ff14', text: '#39ff14' },
  external: { bg: '#0a0a12', stroke: '#ff66ff', text: '#ff66ff' },
  arrow:    { stroke: '#00aaff', label: '#b0b4c4' },
  title:    { color: '#ffff00' },
  canvas:   '#0a0a12',
};

let seed = 1000;
const nextSeed = () => ++seed;
const nextId = (prefix) => `${prefix}-${nextSeed()}`;

function makeShape(id, type, x, y, w, h, colors, strokeW = 2, roundness = null) {
  const el = {
    id, type, x, y, width: w, height: h,
    angle: 0, strokeColor: colors.stroke, backgroundColor: colors.bg,
    fillStyle: 'solid', strokeWidth: strokeW, roughness: 0, opacity: 100,
    groupIds: [], boundElements: [], seed: nextSeed(), version: 1, versionNonce: nextSeed(),
    isDeleted: false, locked: false, link: null, updated: Date.now(),
  };
  if (roundness) el.roundness = roundness;
  return el;
}

function makeText(id, text, fontSize, color, containerId, groupIds = []) {
  const lines = text.split('\n');
  const maxLen = Math.max(...lines.map(l => l.length));
  const w = Math.round(maxLen * fontSize * 0.6) + 20;
  const h = lines.length * (fontSize + 4) + 8;
  return {
    id, type: 'text', x: 0, y: 0, width: w, height: h,
    angle: 0, strokeColor: color, backgroundColor: 'transparent',
    fillStyle: 'solid', strokeWidth: 1, roughness: 0, opacity: 100,
    text, fontSize, fontFamily: 3, textAlign: 'center', verticalAlign: 'middle',
    containerId, originalText: text, lineHeight: 1.2,
    groupIds, boundElements: [], seed: nextSeed(), version: 1, versionNonce: nextSeed(),
    isDeleted: false, locked: false, link: null, updated: Date.now(),
  };
}

function makeArrow(id, x, y, points, startId, endId) {
  return {
    id, type: 'arrow', x, y, width: 0, height: 0,
    angle: 0, strokeColor: theme.arrow.stroke, backgroundColor: 'transparent',
    fillStyle: 'solid', strokeWidth: 2, roughness: 0, opacity: 100,
    points, startBinding: startId ? { elementId: startId, focus: 0, gap: 8 } : null,
    endBinding: endId ? { elementId: endId, focus: 0, gap: 8 } : null,
    endArrowhead: 'arrow', startArrowhead: null,
    groupIds: [], boundElements: [], seed: nextSeed(), version: 1, versionNonce: nextSeed(),
    isDeleted: false, locked: false, link: null, updated: Date.now(),
  };
}

function makeFreeText(id, text, x, y, fontSize, color) {
  const lines = text.split('\n');
  const maxLen = Math.max(...lines.map(l => l.length));
  return {
    id, type: 'text', x, y,
    width: Math.round(maxLen * fontSize * 0.6) + 20,
    height: lines.length * (fontSize + 4) + 8,
    angle: 0, strokeColor: color, backgroundColor: 'transparent',
    fillStyle: 'solid', strokeWidth: 1, roughness: 0, opacity: 100,
    text, fontSize, fontFamily: 3, textAlign: 'center', verticalAlign: 'top',
    containerId: null, originalText: text, lineHeight: 1.2,
    groupIds: [], boundElements: [], seed: nextSeed(), version: 1, versionNonce: nextSeed(),
    isDeleted: false, locked: false, link: null, updated: Date.now(),
  };
}

// ── Layout coordinates ──
const elements = [];
const arrowBindings = {}; // shapeId -> arrow ids

function addShapeWithLabel(id, type, x, y, w, h, label, colors, strokeW = 2, roundness = null) {
  const txtId = `txt-${id}`;
  const grpId = `grp-${id}`;
  const shape = makeShape(id, type, x, y, w, h, colors, strokeW, roundness);
  shape.groupIds = [grpId];
  shape.boundElements.push({ type: 'text', id: txtId });
  const text = makeText(txtId, label, 14, colors.text, id, [grpId]);
  elements.push(shape, text);
  arrowBindings[id] = shape;
  return { cx: x + w/2, cy: y + h/2 };
}

function addArrow(fromId, toId, fromCenter, toCenter, label) {
  const aId = nextId('arrow');
  const dx = toCenter.cx - fromCenter.cx;
  const dy = toCenter.cy - fromCenter.cy;
  const arrow = makeArrow(aId, fromCenter.cx, fromCenter.cy, [[0,0],[dx,dy]], fromId, toId);

  if (label) {
    const lblId = `lbl-${aId}`;
    const lblText = makeText(lblId, label, 11, theme.arrow.label, aId);
    arrow.boundElements.push({ type: 'text', id: lblId });
    elements.push(lblText);
  }

  // Update source/target boundElements
  const src = arrowBindings[fromId];
  const tgt = arrowBindings[toId];
  if (src) src.boundElements.push({ type: 'arrow', id: aId });
  if (tgt) tgt.boundElements.push({ type: 'arrow', id: aId });

  elements.push(arrow);
}

// ── Title ──
elements.push(makeFreeText('title', 'WS-Tracker Level 1 Data Flow Diagram', 420, -60, 24, theme.title.color));

// ── Column Labels ──
elements.push(makeFreeText('col-src',  'DATA SOURCE',   80, -20, 12, '#b0b4c4'));
elements.push(makeFreeText('col-ing',  'INGESTION',    380, -20, 12, '#b0b4c4'));
elements.push(makeFreeText('col-stor', 'PERSISTENCE',  730, -20, 12, '#b0b4c4'));
elements.push(makeFreeText('col-pres', 'PRESENTATION',1070, -20, 12, '#b0b4c4'));
elements.push(makeFreeText('col-user', 'CONSUMERS',   1400, -20, 12, '#b0b4c4'));

// ── External Entities (magenta, thick border) ──
const e1 = addShapeWithLabel('ext-e1', 'rectangle', 40, 300, 180, 100, 'E1: WS API\n(DDOProtocol)', theme.external, 3);
const e2 = addShapeWithLabel('ext-e2', 'rectangle', 1380, 100, 180, 100, 'E2: Admin /\nManager', theme.external, 3);
const e3 = addShapeWithLabel('ext-e3', 'rectangle', 1380, 400, 180, 100, 'E3: Planner', theme.external, 3);
const e4 = addShapeWithLabel('ext-e4', 'rectangle', 1380, 620, 180, 100, 'E4: General\nForeman', theme.external, 3);

// ── Processes (cyan, ellipses) ──
const p1 = addShapeWithLabel('proc-1', 'ellipse', 340, 60,  220, 100, '1.0 Data\nCollection', theme.process);
const p2 = addShapeWithLabel('proc-2', 'ellipse', 340, 260, 220, 100, '2.0 Query\nEngine', theme.process);
const p3 = addShapeWithLabel('proc-3', 'ellipse', 340, 460, 220, 100, '3.0 Metrics\nEngine', theme.process);
const p4 = addShapeWithLabel('proc-4', 'ellipse', 340, 680, 220, 100, '4.0 Ghost\nDetection', theme.process);
const p5 = addShapeWithLabel('proc-5', 'ellipse', 1020, 220, 220, 120, '5.0 Hub\nOrchestration', theme.process);
const p6 = addShapeWithLabel('proc-6', 'ellipse', 1020, 480, 220, 120, '6.0 Detail\nResolution', theme.process);

// ── Data Stores (green, rectangles) ──
const rnd = { type: 3, value: 4 };
const d1 = addShapeWithLabel('ds-d1', 'rectangle', 700, 20,  200, 70, 'D1: Assessments', theme.store, 2, rnd);
const d2 = addShapeWithLabel('ds-d2', 'rectangle', 700, 150, 200, 70, 'D2: Monitoring', theme.store, 2, rnd);
const d3 = addShapeWithLabel('ds-d3', 'rectangle', 700, 280, 200, 70, 'D3: Snapshots', theme.store, 2, rnd);
const d4 = addShapeWithLabel('ds-d4', 'rectangle', 700, 420, 200, 70, 'D4: Users', theme.store, 2, rnd);
const d5 = addShapeWithLabel('ds-d5', 'rectangle', 700, 560, 200, 70, 'D5: Career JSON', theme.store, 2, rnd);
const d6 = addShapeWithLabel('ds-d6', 'rectangle', 700, 720, 200, 70, 'D6: Cache Layer', theme.store, 2, rnd);

// ── Data Flow Arrows ──

// E1 → Processes (external API feeds ingestion)
addArrow('ext-e1', 'proc-1', e1, p1, 'Raw Assessment\nData');
addArrow('ext-e1', 'proc-2', e1, p2, 'On-demand\nSQL Queries');

// Ingestion → Data Stores
addArrow('proc-1', 'ds-d1', p1, d1, 'Persist\nAssessments');
addArrow('proc-1', 'ds-d2', p1, d2, 'Monitor\nRecords');
addArrow('proc-2', 'ds-d3', p2, d3, 'Persist\nSnapshots');
addArrow('proc-2', 'ds-d6', p2, d6, 'Cache\nResults');

// Metrics ↔ Career JSON (bidirectional)
addArrow('proc-3', 'ds-d5', p3, d5, 'Export JSON');
addArrow('ds-d5', 'proc-3', d5, p3, 'Read JSON');

// Ghost → Monitoring store
addArrow('proc-4', 'ds-d2', p4, d2, 'Ghost\nEvidence');

// Data Stores → Hub Orchestration
addArrow('ds-d1', 'proc-5', d1, p5, 'Assessment\nData');
addArrow('ds-d2', 'proc-5', d2, p5, 'Monitor\nData');
addArrow('ds-d3', 'proc-5', d3, p5, 'Snapshot\nData');
addArrow('ds-d6', 'proc-5', d6, p5, 'Cached\nAggregates');

// Metrics → Hub (direct, not via store)
addArrow('proc-3', 'proc-5', p3, p5, 'Planner\nMetrics');

// Data Stores → Detail Resolution
addArrow('ds-d1', 'proc-6', d1, p6, 'Entity\nLookups');
addArrow('ds-d4', 'proc-6', d4, p6, 'User\nData');

// Presentation → External Users
addArrow('proc-5', 'ext-e2', p5, e2, 'Hub Pages');
addArrow('proc-5', 'ext-e4', p5, e4, 'Regional\nHubs');
addArrow('proc-6', 'ext-e2', p6, e2, 'Detail\nViews');
addArrow('proc-6', 'ext-e3', p6, e3, 'Personal\nViews');

// ── Build final file ──
const excalidraw = {
  type: 'excalidraw',
  version: 2,
  source: 'WS-TrackerV1-DFD',
  elements,
  appState: {
    viewBackgroundColor: theme.canvas,
    theme: 'dark',
    gridSize: 20,
    gridColor: { Bold: '#1a1a2e', Regular: '#0f0f1a' },
  },
  files: {},
};

const outPath = process.argv[2] || './dataflow-level1.excalidraw';
writeFileSync(outPath, JSON.stringify(excalidraw, null, 2));
console.log(`✓ Generated ${outPath} with ${elements.length} elements`);
