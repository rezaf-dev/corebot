const fs = require('fs');
const path = require('path');

const widgetPath = path.resolve(__dirname, '../../public/widget.js');
const widgetSource = fs.readFileSync(widgetPath, 'utf8');

function assertIncludes(expected, label) {
    if (!widgetSource.includes(expected)) {
        throw new Error(`${label}: expected widget source to include ${JSON.stringify(expected)}`);
    }
}

assertIncludes('renderFrame = requestAnimationFrame(flushPendingDelta)', 'frame-batched rendering');
assertIncludes("bubble.textContent = raw", 'plain text while streaming');
assertIncludes("bubble.dir = messageDirection(raw)", 'direction updated while streaming');
assertIncludes("setBubbleText(node, getBubble(node)?.dataset.rawText || '')", 'final markdown rendering');
assertIncludes('page: pageContext()', 'page context sent with streamed messages');

console.log('widget-streaming tests passed');
