import * as THREE from 'three';

/** Own every resource in a complete table and batch its static furniture parts. */
export function createPaidTableModel(scene: THREE.Scene, name: string) {
    const root = new THREE.Group();
    root.name = name;
    const geometries = new Set<THREE.BufferGeometry>();
    const materials = new Set<THREE.Material>();
    const textures = new Set<THREE.Texture>();
    const batches = new Map<
        string,
        {
            geometry: THREE.BufferGeometry;
            material: THREE.Material;
            matrices: THREE.Matrix4[];
        }
    >();
    const transform = new THREE.Object3D();
    let disposed = false;
    let finished = false;

    function geometry<T extends THREE.BufferGeometry>(value: T): T {
        geometries.add(value);
        return value;
    }
    function material(color: number, metalness = 0, roughness = 0.75) {
        const value = new THREE.MeshStandardMaterial({
            color,
            metalness,
            roughness,
        });
        materials.add(value);
        return value;
    }
    function texture(
        width: number,
        height: number,
        paint: (ctx: CanvasRenderingContext2D) => void,
    ) {
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        if (!ctx) throw new Error(`${name} textures unavailable`);
        paint(ctx);
        const value = new THREE.CanvasTexture(canvas);
        value.colorSpace = THREE.SRGBColorSpace;
        value.anisotropy = 4;
        textures.add(value);
        return value;
    }
    function part(
        shape: THREE.BufferGeometry,
        finish: THREE.Material,
        x = 0,
        y = 0,
        z = 0,
        sx = 1,
        sy = 1,
        sz = 1,
        rx = 0,
        ry = 0,
        rz = 0,
    ) {
        if (finished || disposed)
            throw new Error('Table construction is closed');
        geometries.add(shape);
        materials.add(finish);
        const key = `${shape.uuid}:${finish.uuid}`;
        let batch = batches.get(key);
        if (!batch) {
            batch = { geometry: shape, material: finish, matrices: [] };
            batches.set(key, batch);
        }
        transform.position.set(x, y, z);
        transform.rotation.set(rx, ry, rz);
        transform.scale.set(sx, sy, sz);
        transform.updateMatrix();
        batch.matrices.push(transform.matrix.clone());
    }
    function finish() {
        if (finished || disposed) return;
        for (const batch of batches.values()) {
            const mesh = new THREE.InstancedMesh(
                batch.geometry,
                batch.material,
                batch.matrices.length,
            );
            // Register immediately so construction failures can release instance buffers.
            root.add(mesh);
            batch.matrices.forEach((matrix, index) =>
                mesh.setMatrixAt(index, matrix),
            );
            mesh.instanceMatrix.needsUpdate = true;
            mesh.computeBoundingSphere();
        }
        batches.clear();
        scene.add(root);
        finished = true;
    }
    function dispose() {
        if (disposed) return;
        disposed = true;
        root.removeFromParent();
        root.children.forEach((child) => {
            if (child instanceof THREE.InstancedMesh) child.dispose();
        });
        geometries.forEach((value) => value.dispose());
        materials.forEach((value) => value.dispose());
        textures.forEach((value) => value.dispose());
        geometries.clear();
        materials.clear();
        textures.clear();
        batches.clear();
        root.clear();
    }
    return { root, geometry, material, texture, part, finish, dispose };
}
