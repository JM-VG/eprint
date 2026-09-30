import {
    ACESFilmicToneMapping,
    AmbientLight,
    BufferAttribute,
    BufferGeometry,
    Color,
    DirectionalLight,
    Group,
    Mesh,
    MeshStandardMaterial,
    PerspectiveCamera,
    Scene,
    SRGBColorSpace,
    TextureLoader,
    WebGLRenderer,
} from 'three';
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js';

const FACE = 0;
const BACK = 1;
const RIM = 2;

// Regroup the STL's triangles into front face / back face / rim and give the faces planar UVs,
// so the flat logo PNG can be wrapped onto the coin.
function coinGeometry(source) {
    source.computeBoundingBox();
    const { min, max } = source.boundingBox;
    const pos = source.getAttribute('position');
    const normal = source.getAttribute('normal');
    const buckets = [[], [], []];

    for (let t = 0; t < pos.count; t += 3) {
        const nz = normal.getZ(t);
        buckets[nz > 0.9 ? FACE : nz < -0.9 ? BACK : RIM].push(t);
    }

    const positions = [];
    const uvs = [];
    const geometry = new BufferGeometry();
    let start = 0;

    buckets.forEach((triangles, group) => {
        for (const t of triangles) {
            for (let v = t; v < t + 3; v++) {
                const x = pos.getX(v);
                const y = pos.getY(v);
                positions.push(x, y, pos.getZ(v));
                const u = (x - min.x) / (max.x - min.x);
                uvs.push(group === BACK ? 1 - u : u, (y - min.y) / (max.y - min.y));
            }
        }
        geometry.addGroup(start, triangles.length * 3, group);
        start += triangles.length * 3;
    });

    geometry.setAttribute('position', new BufferAttribute(new Float32Array(positions), 3));
    geometry.setAttribute('uv', new BufferAttribute(new Float32Array(uvs), 2));
    geometry.computeVertexNormals();
    geometry.center();

    return geometry;
}

const easeOutCubic = (t) => 1 - (1 - t) ** 3;

export function mountLogoCoin(canvas) {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const renderer = new WebGLRenderer({ canvas, antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = SRGBColorSpace;
    renderer.toneMapping = ACESFilmicToneMapping;

    const scene = new Scene();
    const camera = new PerspectiveCamera(30, 1, 1, 1000);
    camera.position.set(0, 0, 230);

    scene.add(new AmbientLight(0xffffff, 1.1));
    const key = new DirectionalLight(0xfff6cc, 2.4);
    key.position.set(-80, 120, 160);
    scene.add(key);
    const rim = new DirectionalLight(new Color('#E8692A'), 2.2);
    rim.position.set(160, -40, -80);
    scene.add(rim);

    // Resting pose echoes the reference: turned toward the card, tipped back a little.
    const pose = new Group();
    pose.rotation.set(-0.18, -0.55, 0.12);
    scene.add(pose);

    const spinner = new Group();
    pose.add(spinner);

    const texture = new TextureLoader().load(canvas.dataset.texture);
    texture.colorSpace = SRGBColorSpace;
    texture.anisotropy = renderer.capabilities.getMaxAnisotropy();

    const face = new MeshStandardMaterial({ map: texture, roughness: 0.45, metalness: 0.05 });
    const edge = new MeshStandardMaterial({ color: 0xc9c9c4, roughness: 0.32, metalness: 0.75 });

    let coin = null;
    let spinStart = null;
    const pointer = { x: 0, y: 0 };

    new STLLoader().load(canvas.dataset.model, (source) => {
        coin = new Mesh(coinGeometry(source), [face, face, edge]);
        spinner.add(coin);
        spinStart = performance.now();
        canvas.classList.add('is-ready');
    });

    const resize = () => {
        const { clientWidth: w, clientHeight: h } = canvas;
        if (!w || !h) {
            return;
        }
        renderer.setSize(w, h, false);
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
    };
    new ResizeObserver(resize).observe(canvas);
    resize();

    window.addEventListener('pointermove', (event) => {
        pointer.x = event.clientX / window.innerWidth - 0.5;
        pointer.y = event.clientY / window.innerHeight - 0.5;
    });

    const SPIN_MS = 1800;
    renderer.setAnimationLoop((now) => {
        if (coin) {
            const t = reduced ? 1 : Math.min(1, (now - spinStart) / SPIN_MS);
            spinner.rotation.y = (1 - easeOutCubic(t)) * -Math.PI * 2;
            if (!reduced) {
                spinner.position.y = Math.sin(now / 1400) * 2.5;
                pose.rotation.x += (-0.18 + pointer.y * 0.25 - pose.rotation.x) * 0.05;
                pose.rotation.y += (-0.55 + pointer.x * 0.35 - pose.rotation.y) * 0.05;
            }
        }
        renderer.render(scene, camera);
    });
}
