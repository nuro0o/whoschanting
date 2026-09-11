# Mirror character creator art

The current body types and expressive pose artwork are documented in [character-creator-poses-art.md](character-creator-poses-art.md). The original face and outfit sheets below are retained as historical assets; the renderer now uses the six coordinated body/pose sets. Front-facing hair, hats and details remain in use.

Generated using the built-in `image_gen.imagegen` tool on 2026-09-12. No CLI/API fallback, external image services, or programmatic image editing was used. Alpha transparency was inspected from the generated files. Source portraits were visually inspected for painterly style; no existing portrait was edited.

## Faces — `public/assets/chanting/creator/faces.png`

Source: `C:/Users/nurdo/.codex/generated_images/01a0927e-9efb-74e3-9794-e48478bd384c/exec-2dcc9ab8-0e85-4db2-9fa0-a8edf0099180.png`

Exact prompt:

```text
Use case: stylized-concept. Asset type: production transparent layered 2D character creator sprite atlas, NOT a presentation.
Create a portrait-format 1024x1280 PNG with genuine alpha transparency. Four cells in an exact seamless 2-column 2-row grid, each 512x640. All empty space completely transparent (alpha0), never draw checkerboard, paper, gridlines, text or background.
Each cell contains ONLY a bald human head, ears and neck in neutral grayscale painted illustration. Same head dimensions/pose across all four: perfectly straight-on front view, upright, centered x256, top of bald skull y115, eyes y220, chin y315, neck tapers to x216..296 and extends to y370. Transparent below neck, no shoulders/clothes/body. Head plus ears width approx190px (x161..351). Keep generous transparent space above, beside, below, essential for matching independent clothing and hair layers.
Cells reading order: top left 'harbor': calm androgynous adult soft square face, strong brows; top right 'weathered': older masculine face with fine wrinkles and angular cheeks; bottom left 'keen': feminine narrow oval face, alert eyes, subtle smile; bottom right 'round': friendly broad round adult face full cheeks. All faces have the SAME outer skull size and chin/ear/eye landmarks. No hair, no beard, no hats, no accessories. No detached or grotesque horror; warm living people.
Style: high quality stylized painterly coastal folk-horror game character art, handcrafted gouache brushwork, clear inked contours, gently exaggerated expressive faces, directional upper-left soft light, subtle shaded planes, not photoreal, not anime, not flat vector. Grayscale-only facial surfaces with mid-light gray skin, dark gray contours, eyes white and charcoal to permit a color overlay later. Prioritize identical geometry and clean cutout transparency.
```

## Outfits — `public/assets/chanting/creator/outfits.png`

Source: `C:/Users/nurdo/.codex/generated_images/01a0927e-9efb-74e3-9794-e48478bd384c/exec-597b56c2-159f-43e7-bf5d-1d44268912de.png`

Exact prompt:

```text
Use case: stylized-concept. Asset type: production modular game clothing sprite atlas.
Create one transparent PNG clothing atlas, portrait 1024x1280, exact 2x2 equal cells. Each cell is a full figure canvas 512x640. Draw only the garment, as worn on an INVISIBLE front-facing mannequin, cut out with real alpha transparency; no head, no skin, no hands, no mannequin, no backgrounds, no checkerboard, no cell borders, no text.
CRUCIAL POSITION: In each cell TOP HALF from y0 through y325 must be completely empty transparent. All four outfits occupy only LOWER HALF of each cell: neckline at (256,340), shoulder tips (110,375) and (402,375), sleeves extend to x75 andx437, broad hem cropped at bottom y640. Neck opening is empty transparent oval x219..293,y327..358. Centerline perfectly vertical at x256. The outfit shoulders and waist match across cells. This atlas will layer beneath a separately drawn head: never draw face or hair.
Four 18th-century coastal folk-horror villager outfits reading order:
top left: mariner's short heavy peacoat with wide folded lapels over plain shirt, rope-button closure;
top right: scholar's high collared tailored coat over softly pleated shirt, modest embroidery;
bottom left: buttoned waistcoat over full sleeve linen shirt, rolled cuffs, neat shirt collar;
bottom right: ceremonial high collared robe with angular overlapping stole and subtle wave embroidery, no occult symbols/text.
Material palette ENTIRELY NEUTRAL GRAYSCALE (mid gray fabrics, lightergray shirt details, darkgray ink outlines) so each outfit can be separately color-tinted. Painterly gouache brushstrokes, clear silhouettes, handsome stitched fabric detail, soft upper-left light, slightly exaggerated storybook realism. Aim for matching dark coastal hand-painted character portraits, not modern fashion, not photoreal, not anime. Every cell same perfectly forward facing pose, empty top half, transparent background alpha.
```

## Hair � `public/assets/chanting/creator/hair.png`

Source: `C:/Users/nurdo/.codex/generated_images/01a0927e-9efb-74e3-9794-e48478bd384c/exec-584396c0-9504-4d59-bb98-93be240f83cc.png`

Exact prompt:

```text
Use case: stylized-concept. Asset type: transparent modular character hair sprite atlas.
Create a landscape 1536x1024 PNG with real alpha transparency, exact 3 columns x2 rows of square 512x512 cells. Draw five isolated wigs, front view, sixth bottomrightcell completely empty transparent. Grid lines/labels/background/checkerboard must NEVER be painted.
Each visible wig sits around an invisible front-facing oval adult head: hair top atx256,y90, templesx145and367,y220, chin level y420. No face, head, skin, neck, clothes, mannequin or accessories. The face opening is empty alpha transparent. All wigs share the same forehead width and scalp curvature. Paint only hair. Keep hair inside its own cell, no bleed. Same matching heads acrossallstyles. Nothing behind faceopening. No labels.
Reading order top left: short cropped tousled hair with textured fringe; top middle: shoulder length wavy hair parted slightly left, open center for face; top right: short full curly hair, rounded tightcurltexture, open face;
bottom left: tidy parted hair with a single thick braid falling down the viewer-rightside, face regionfullytransparent; bottom middle: swept-back side-parted hair with a distinctive raised forelock, no face; bottom right: COMPLETELY EMPTY alpha transparent cell, no baldhead.
Material palette neutral GRAYSCALE, medium gray hair with darkgray inkshadows and pale highlights so CSS tint can recolor. Coastal folkhorror storybook painterly gouache illustration, clear brushwork and detailedstrands, softlydirectional upperleft lighting, same semi-realistic proportions as painted villageportraits, no anime, no photorealism, no vector. Essential: truly transparent background and transparent interior facespaces.
```

## Hats � `public/assets/chanting/creator/hats.png`

Source: `C:/Users/nurdo/.codex/generated_images/01a0927e-9efb-74e3-9794-e48478bd384c/exec-5b025e03-8584-4d7c-8d09-62c4f44702f9.png`

Exact prompt:

```text
Use case: stylized-concept. Asset type: game paper-doll hats transparent sprite atlas.
Produce one PNG with genuine alpha transparency, landscape1536x1024, exact3columns2rows square512x512cells. Six slotsreadingorder: top-left EMPTY fullytransparent (no item); top-middle a dark teal knitted fisherman's watchcap; top-right a charcoal leather broad-brimmed hat; bottom-left a worn brown leather tricorn with modest brassbutton; bottom-middle a deep mossgreen cloth hood with FACE OPENING CUT OUT to realalpha; bottom-right a delicate tarnishedbrass circlet with two short branched driftwood antlers and tiny darkseaweedties.
Isolated hats ONLY, seen perfectly straight-on as though worn by the same invisible humanhead. No head, face, skin, hair, body, mannequin, scenery, grid, labels, checkerboard, floor, shadowsoutsideobject, mist, glow orbackground. Each item stayswithinitscell. Hats fit same rounded foreheadwidtharound240px, fromx136..376 at foreheadliney280. Hatcrowny140 orhigher, hatsbasey300. Hood extendsdown toy470 at sides, large transparent facehole x145..367,y200..420; crownony120. Tricorn/widebrim widtharound430px. Antlers keep withinpadding25px.
Style: painterly coastal folkhorror storybook illustration, detailedgouachebrushwork, darkinkcontours, texturedcloth/leather, consistent upper-leftsoftlight. Muted realcolors on hats (not grayscale), tastefullyaged, not extravagantfantasy, no modernfashion, no photoreal, noanime. All background and faceopenings real alpha transparency, do not paint a backdrop orcheckerpattern.
```

## Details � `public/assets/chanting/creator/details.png`

Source: `C:/Users/nurdo/.codex/generated_images/01a0927e-9efb-74e3-9794-e48478bd384c/exec-342410b2-310e-4b99-92b1-0be8336663b4.png`

Exact prompt:

```text
Use case: stylized-concept. Asset type: game cosmetic accessories transparent sprite atlas.
Create square1024x1024PNG, actual alpha transparent background, exact2x2grid of equal512x512cells. Top-left cell fullyemptytransparent. Top-right one pair of small antique round spectacles with thin mutedbrasswireframes, perfectlyfrontview, twocircular clear openings whose lensinteriors are fullyalphatransparent, width330px height100px centeredcell. Bottomleft one small closed brass hoop earring, single hoop, frontview, outerheight170px,width110px centeredcell. Bottomright one ornate wave-shaped ovalbrassbrooch with a small darkteal glasscabochon, height200px,width155px centeredcell.
Draw ONLY three accessories. No face, head, body, mannequin, scenery, background, cellborders, gridlines, labels, lettering, checkerboard, shadows outside object, atmosphere or glow. Each isolated object centereditscell with generous completely transparent margins. Painterly coastal folkhorror storybook gameart, detailedbrushedmetal with tinywearmarks, darkbrown outlines, refined tactilegouachebrushwork, upperleftsoftlight, clearreadable silhouettes, mutedbrass andtealcolors. Spectacles need oneverythin curvedbridge, emptytransparent lensholes. These items will be layered over independent characterportraits; transparency is essential.
```

## Integration and inspection

All five PNGs preserve their generated alpha channels. Image files were only copied, never cropped/recolored/edited programmatically. CSS crops and per-layer tinting are applied by the shared character renderer. Transparent space was verified by reading alpha values, including face openings and unused cells. Original output dimensions are faces/outfits 1122�1402, hair/hats 1536�1024, details 1254�1254. Some generated sprites cross nominal cell boundaries; the renderer uses individually measured crop rectangles rather than assuming uniform atlas slots. Geometry is intentionally defined in code and should be checked whenever replacing art.

The four faces use neutral grayscale for independent skin tinting. Hair and outfits use neutral grayscale for their separate colors; hats and accessories retain muted material colors. The bald hair and none hat/detail options render no layer. Existing finished character portraits are preserved.
