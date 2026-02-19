import { writeFileSync } from 'fs';

// Theme: Just Black Neon
const theme = {
  hub:      { bg: '#0a0a12', stroke: '#00ffff', text: '#00ffff' },
  detail:   { bg: '#0a0a12', stroke: '#39ff14', text: '#39ff14' },
  role:     { bg: '#0a0a12', stroke: '#ff66ff', text: '#ff66ff' },
  onboard:  { bg: '#0a0a12', stroke: '#ff8800', text: '#ff8800' },
  settings: { bg: '#0a0a12', stroke: '#ffff00', text: '#ffff00' },
  arrow:    { stroke: '#00aaff', label: '#b0b4c4' },
  title:    { color: '#ffff00' },
  canvas:   '#0a0a12',
};

let seed = 2000;
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
  return {
    id, type: 'text', x: 0, y: 0,
    width: Math.round(maxLen * fontSize * 0.6) + 20,
    height: lines.length * (fontSize + 4) + 8,
    angle: 0, strokeColor: color, backgroundColor: 'transparent',
    fillStyle: 'solid', strokeWidth: 1, roughness: 0, opacity: 100,
    text, fontSize, fontFamily: 3, textAlign: 'center', verticalAlign: 'middle',
    containerId, originalText: text, lineHeight: 1.2,
    groupIds, boundElements: [], seed: nextSeed(), version: 1, versionNonce: nextSeed(),
    isDeleted: false, locked: false, link: null, updated: Date.now(),
  };
}

function makeArrow(id, x, y, points, startId, endId, color = theme.arrow.stroke) {
  return {
    id, type: 'arrow', x, y, width: 0, height: 0,
    angle: 0, strokeColor: color, backgroundColor: 'transparent',
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

const elements = [];
const shapes = {};

function addShape(id, type, x, y, w, h, label, colors, strokeW = 2, roundness = null) {
  const txtId = `txt-${id}`;
  const grpId = `grp-${id}`;
  const shape = makeShape(id, type, x, y, w, h, colors, strokeW, roundness);
  shape.groupIds = [grpId];
  shape.boundElements.push({ type: 'text', id: txtId });
  const text = makeText(txtId, label, 13, colors.text, id, [grpId]);
  elements.push(shape, text);
  shapes[id] = shape;
  return { cx: x + w/2, cy: y + h/2 };
}

function addArrowBetween(fromId, toId, fromC, toC, label, color) {
  const aId = nextId('nav');
  const dx = toC.cx - fromC.cx;
  const dy = toC.cy - fromC.cy;
  const arrow = makeArrow(aId, fromC.cx, fromC.cy, [[0,0],[dx,dy]], fromId, toId, color || theme.arrow.stroke);

  if (label) {
    const lblId = `lbl-${aId}`;
    const lblText = makeText(lblId, label, 10, theme.arrow.label, aId);
    arrow.boundElements.push({ type: 'text', id: lblId });
    elements.push(lblText);
  }

  if (shapes[fromId]) shapes[fromId].boundElements.push({ type: 'arrow', id: aId });
  if (shapes[toId]) shapes[toId].boundElements.push({ type: 'arrow', id: aId });
  elements.push(arrow);
}

const rnd = { type: 3, value: 8 };

// ── Title ──
elements.push(makeFreeText('title', 'WS-Tracker Navigation Flow — Admin/Manager View', 300, -80, 22, theme.title.color));

// ── Section Labels ──
elements.push(makeFreeText('sec-entry', 'ENTRY POINTS', 20, -30, 11, '#b0b4c4'));
elements.push(makeFreeText('sec-hubs', 'HUB PAGES', 360, -30, 11, '#b0b4c4'));
elements.push(makeFreeText('sec-detail', 'DETAIL VIEWS', 820, -30, 11, '#b0b4c4'));

// ══ COLUMN 1: Entry Points ══

// Onboarding flow (orange)
const onb = addShape('onb-login', 'rectangle', 20, 40, 160, 60, 'Login', theme.onboard, 2, rnd);
const onb2 = addShape('onb-onboard', 'rectangle', 20, 140, 160, 60, 'Onboarding\n(4 steps)', theme.onboard, 2, rnd);
const onb3 = addShape('onb-pref', 'rectangle', 20, 260, 160, 60, 'Dashboard\nPreference', theme.onboard, 2, rnd);

// Settings (yellow)
const stg = addShape('settings', 'rectangle', 20, 680, 160, 60, 'Settings /\nPreferences', theme.settings, 2, rnd);

// ══ COLUMN 2: Hub Pages (cyan) ══
const hub1 = addShape('hub-dash', 'ellipse', 320, 40, 200, 80, 'Dashboard\nHub', theme.hub);
const hub2 = addShape('hub-plan', 'ellipse', 320, 180, 200, 80, 'Planners\nHub', theme.hub);
const hub3 = addShape('hub-assess', 'ellipse', 320, 320, 200, 80, 'Assessments\nHub', theme.hub);
const hub4 = addShape('hub-monitor', 'ellipse', 320, 460, 200, 80, 'Monitoring\nHub', theme.hub);
const hub5 = addShape('hub-admin', 'ellipse', 320, 600, 200, 80, 'Admin\nHub', theme.hub);

// ══ COLUMN 3: Detail Views (green) ══
const det1 = addShape('det-planner', 'rectangle', 760, 60, 180, 70, 'Planner\nDetail', theme.detail, 2, rnd);
const det2 = addShape('det-region', 'rectangle', 760, 180, 180, 70, 'Region\nDetail', theme.detail, 2, rnd);
const det3 = addShape('det-circuit', 'rectangle', 760, 300, 180, 70, 'Circuit\nDetail', theme.detail, 2, rnd);
const det4 = addShape('det-assess', 'rectangle', 760, 420, 180, 70, 'Assessment\nDetail', theme.detail, 2, rnd);
const det5 = addShape('det-ghost', 'rectangle', 760, 540, 180, 70, 'Ghost\nDetail', theme.detail, 2, rnd);

// ══ Admin tools (inside admin hub scope) ══
const adm1 = addShape('adm-users', 'rectangle', 760, 640, 160, 50, 'User Mgmt', theme.detail, 1, rnd);
const adm2 = addShape('adm-cache', 'rectangle', 760, 710, 160, 50, 'Cache Controls', theme.detail, 1, rnd);
const adm3 = addShape('adm-query', 'rectangle', 760, 780, 160, 50, 'Query Explorer', theme.detail, 1, rnd);

// ══ ARROWS: Entry → Hubs ══
addArrowBetween('onb-login', 'onb-onboard', onb, onb2, 'first login', '#ff8800');
addArrowBetween('onb-onboard', 'onb-pref', onb2, onb3, 'new step', '#ff8800');
addArrowBetween('onb-pref', 'hub-dash', onb3, hub1, 'default\nlanding', '#ff8800');
addArrowBetween('onb-pref', 'hub-plan', onb3, hub2, null, '#ff8800');
addArrowBetween('onb-pref', 'hub-assess', onb3, hub3, null, '#ff8800');

// Settings ↔ Hubs
addArrowBetween('settings', 'hub-dash', stg, hub1, 'change\ndefault', '#ffff00');

// ══ ARROWS: Hub → Hub (cross-links on dashboard) ══
addArrowBetween('hub-dash', 'hub-plan', hub1, hub2, 'planner\nsnapshot');
addArrowBetween('hub-dash', 'hub-assess', hub1, hub3, 'assessment\npipeline');
addArrowBetween('hub-dash', 'hub-monitor', hub1, hub4, 'alerts');

// ══ ARROWS: Hub → Detail ══
addArrowBetween('hub-plan', 'det-planner', hub2, det1, 'roster click');
addArrowBetween('hub-assess', 'det-region', hub3, det2, 'region card');
addArrowBetween('hub-assess', 'det-circuit', hub3, det3, 'circuit card');
addArrowBetween('hub-monitor', 'det-ghost', hub4, det5, 'ghost card');
addArrowBetween('hub-admin', 'adm-users', hub5, adm1, null);
addArrowBetween('hub-admin', 'adm-cache', hub5, adm2, null);
addArrowBetween('hub-admin', 'adm-query', hub5, adm3, null);

// ══ ARROWS: Detail → Detail (cross-linking drill-down) ══
addArrowBetween('det-planner', 'det-circuit', det1, det3, 'circuit\nname click');
addArrowBetween('det-region', 'det-circuit', det2, det3, 'circuit\ncard');
addArrowBetween('det-circuit', 'det-assess', det3, det4, 'assessment\nGUID click');
addArrowBetween('det-assess', 'det-planner', det4, det1, 'planner\nname click');
addArrowBetween('det-assess', 'det-circuit', det4, det3, 'circuit\nlink');

// ══ Build file ══
const excalidraw = {
  type: 'excalidraw',
  version: 2,
  source: 'WS-TrackerV1-NavFlow',
  elements,
  appState: {
    viewBackgroundColor: theme.canvas,
    theme: 'dark',
    gridSize: 20,
    gridColor: { Bold: '#1a1a2e', Regular: '#0f0f1a' },
  },
  files: {},
};

const outPath = process.argv[2] || './navflow-admin.excalidraw';
writeFileSync(outPath, JSON.stringify(excalidraw, null, 2));
console.log(`✓ Generated ${outPath} with ${elements.length} elements`);
