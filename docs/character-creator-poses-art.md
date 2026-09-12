# Body types and painted poses (2026-09-12)

Version 1 recipes now add `body_type` (`type1`, `type2`) and `pose` (`front`, `three_quarter`, `defiant`). Missing fields use masculine/front defaults. Faces and garments have six coordinated atlases; hair, headwear and spectacles have new angled artwork. All images were generated with the built-in image generator, copied unchanged, and have true RGBA transparency. No raster postprocessing or external image services were used.

`creatorPoseArt.ts` records measured source crops and neck/eye/chin anchors. `creatorLayout.ts` places all parts on the same 400Ã—500 artboard using uniform scale. The renderer layers the rear collar, head, and front collar separately, and hair behind the head plus its foreground fringe, to preserve face visibility and believable garment joins. Source clips exclude adjacent sprite fragments where generated atlas gutters overlap. Skin, hair and clothing are desaturated before tinting; bare skin is never tinted as clothing. Defiant hands are gloved.

`creatorRig.ts` records skull/temple, jaw, ear and painted front-collar landmarks for all 24 heads and 24 garments. Hair and hats use a similarity transform from their headband attachment pair to the selected skull: translation, one scale and rotation, with no stretching. The former fixed watchcap angle and fixed accessory artboard boxes have been removed. The SVG is solely a raster viewport and clipping system, not substitute character illustration. Both mirror and table portraits use the same recipe and native aspect ratios.

## Fitting and occlusion

Heads keep their crown-to-chin scale when changing outfits. The painted neck base overlaps behind the actual front lip of the selected garment, whose source rim is a quadratic curve. The face clip combines a measured jaw contour with the neck entering that rim; it removes the source sprite's flared neck corners without cutting through the chin. Avoid placing a shared rectangular collar opening over every garment.

Hair and loose hoods have rear and front passes. Their overlap follows the selected head's actual raster alpha silhouette, rather than a common rectangular face cutout. The hood is worn back around the crown so its painted inner folds remain behind the face; the lower cloth drapes in front of the shoulders, with a dark backing confined to the hood interior. Enclosed hats suppress hair above their fitted lower headband and compress the exposed side hair into curved temple exits. Antler pendants likewise hang behind the temples, with the brow band in front. Spectacles follow the selected eye/bridge anchor; earrings follow the visible ear lobe; brooches follow the garment.

For new artwork, measure source attachment points before changing placement. The headband pairs represent the skull contact plane (including the implied scalp under hair), not the sprite bounding box or the full outer brim. Keep source crops free of neighboring sprite fragments. Validate at mirror size and in compact portraits: every face/outfit combination, every head with every hat, and both short and long hair. Inspect neck/jaw and hat/temple closeups at 1:1; a passing aspect-ratio test or a small contact sheet does not establish a good fit.

The JS regression suite reconstructs the SVG transform independently to verify that headband, eye and ear attachment points meet their targets, that each neck enters its painted collar, and that clothing cannot rescale a head. The existing RGBA PNG assets are unchanged by this fitting repair.

## Angled hair (three-quarter)

Final source: `C:/Users/nurdo/.codex/generated_images/01a092a9-cd36-7372-86ae-acb120443035/exec-1276b4d8-c108-411f-9fb8-ef93e162ac8c.png`

Exact production prompt:

```text
Use case: stylized-concept. Generate a NEW production modular painted character hair atlas, using reference image 1 for hairstyle identities only, reference image 2 for painterly style. Transparent PNG, 1536x1024, exact 3columns x2rows equal512cells. Real alpha background and face holes; no checkerboard, backdrop, grid or text. Five isolated wigs on an invisible adult head turning 20degrees toward viewer RIGHT: left cheek would be visible, face points slightly right. Wigs follow this clear three-quarter head angle, not frontal. Rows readingorder cropped tousled hair, shoulder-length waves, rounded short curls, a single braid hanging viewer-right, swept-back forelock; lastcell empty. Hair only, absolutely no head/face/skin/ears/mannequin. Same scalp size across wigs: head conceptual x165..355,top110, chin420; wig top65, earslevel300. All wigs fit entirely within512cell with30pxgutter. Neutral grayscale midgray hair with inked shadows and pale gouache highlights for later tint. Coastal folkhorror painted storybook brushwork, matching illustratedvillagers, expressive but realistic texture. Do not paint diffuse background glow.
```

## Angled hair (defiant)

Final source: `C:/Users/nurdo/.codex/generated_images/01a092a9-cd36-7372-86ae-acb120443035/exec-85391224-d6f4-492c-8b17-b6cb94999584.png`

Exact production prompt:

```text
Use case: stylized-concept. New pose variant of reference1 (hair atlas). Preserve its five hairstyles and precise3x2layout with sixthslot empty. Turn the conceptual invisible head FIFTEEN degrees toward viewerLEFT, with raised chin: viewer-righttemple morevisible. Paint genuinelynew hair angle fitting defiant adultportrait, no hair/head mirroring operation. Same headsizeeachcell, nofaces/noears/noskin/noclothing. Five styles cropped,waves,curls,braid,swept inreadingorder. Keep neutralgrayscale for tint. All exterior background and interior face holes MUST BE ACTUAL ALPHA TRANSPARENT. Output RGBA PNG, no checkerboard pattern, no fake painted transparency, no labels. Same1536x1024resolution. Coastalfolkhorror paintedbrushwork matchingreference.
```

## Angled hats (three-quarter)

Final source: `C:/Users/nurdo/.codex/generated_images/01a092a9-cd36-7372-86ae-acb120443035/exec-5f95b6f1-96e5-4664-967a-1d83c75043f5.png`

Exact production prompt:

```text
Use case: stylized-concept. Production modular painted character hats atlas. Reference1 supplies exacthatidentities;reference2paintedcast style. NEW threequarter perspective wornon invisiblehead turning20degrees towardviewerRIGHT (viewerleftsideofheadmorevisible). Real RGBA transparency in exteriorbackground AND hoodfaceopening, no checkerboard, backdrop, glow,text,labels. Landscape1536x1024,3columns2rows512cells. Firstcell empty. Topmiddle fishermanwatchcap darkteal;topright broadbrimcharcoalleatherhat;bottomleft brownleathertricorn;bottommiddle mossclothhood;bottomright brasscircletwithshortdriftwoodantlers. Allsame headscale conceptualforeheadwidth240, headforeheadliney280, noheads/faces/skin/hair/mannequins. Hats whollywithincell30pxgutter, crownsabouty110,baseforeheady300. Hoodextendsy460,emptyfaceholex160..380,y195..410angledright. Upperleftlightgouachebrushwork,darkinkcontours, subduedmaterialcolors. Transparencymustactualalpha, outputRGBA PNG.
```

## Angled hats (defiant)

Final source: `C:/Users/nurdo/.codex/generated_images/01a092a9-cd36-7372-86ae-acb120443035/exec-38c1903e-d568-40c6-87f8-f45be3ed7238.png`

Exact production prompt:

```text
Use case: stylized-concept. New defiant pose variant of this modular painted hatsatlas. Preserve exactlysixslotpositions and samefivehatidentities, top-leftempty. Turn conceptual invisiblehead FIFTEENdegrees towardviewerLEFT and raise chin slightly: oppositeangleto input, viewer-rightside morevisible. Render NEW coordinatedhatperspectives, not simple imageflip. Samewatchcap,widebrim,tricorn,hood,antlercirclet. Nohead,noface,noskin,nohair,nobody. Preserve1536x1024 3columns2rowslayout,realalpha transparentexterior andhoodfaceopening. ALLbackgroundmustactualalpha, no checkerpattern. RGBA PNG. Maintain subduedmaterials,paintedfolkhorrorbrushwork.
```

## Angled spectacles

Final source: `C:/Users/nurdo/.codex/generated_images/01a092a9-cd36-7372-86ae-acb120443035/exec-312837b5-3f88-4bb4-bbf3-4379ce7be59d.png`

Exact production prompt:

```text
Use case: stylized-concept. Production accessorysprite sheet, square1024x1024, two isolated pairs of antique thin roundbrassspectacles matchingreferencepair. Tophalf onepair wornbyinvisibleadultface turned20degreestowardviewerRIGHT, bottomhalf onepair wornbyinvisibleadultface turned15degreestowardviewerLEFT withchinraised. Boththreequarterangles, firstpairleftlensslightlylarger/rounderthanrightlens;secondopposite. Delicatebrasswireframe,narrowbridge,shortearpiecearmswhichattachattemples. Eachpairtotalwidth~600height~210 centeredx512 at y256and768. Nohead,skin,eyes,props,earring,brooch,nobackground. Authenticgouachepaintedbronzehighlights. Background and lensholes REAL ALPHATRANSPARENT. No checkerboard, text, labels. RGBA PNG.
```

## Transparency corrections

RGB checkerboard drafts were rejected. Where necessary, the built-in generator received this exact extraction prompt (hair/hat substituted for the relevant asset):

```text
Use case: background-extraction. Remove the painted checkerboard from this hair atlas, including all interior face holes. Replace it with actual alpha transparency. Preserve every painted hair pixel, size, position, angle, exact cell layout and dimensions. DO NOT generate a checkerboard or colored background. Output RGBA PNG with transparent empty areas, not RGB PNG.
```
# Body type and pose head artwork

Generated with the built-in image_gen tool on 2026-09-12. No API/CLI fallback and no programmatic raster edits. Final assets were copied unchanged from the generated_images directory. Pillow was used read-only to measure PNG dimensions, alpha bounds and validate transparency.

## Final assets

All files live in public/assets/chanting/creator/. All are 1254 x 1254 RGBA PNG with actual alpha range 0–255. Each holds four heads in a 2x2 grid: harbor, weathered, keen, round. Bounding rectangles (alpha >128) and approximate manually judged eye midpoint/chin/neck-bottom landmarks are in resources/js/lib/creatorPoseArt.ts. Coordinates are atlas pixels. Head crown is the crop top. Front is straight-on; three_quarter turns toward viewer right ~20 degrees; defiant turns toward viewer left ~15 degrees with slightly raised chin and confident expression. Each pose is redrawn perspective, not CSS rotation.

| Asset | Accepted generated source |
| --- | --- |
| faces-type1-front-v2.png | exec-12420570-333c-4c5f-9fda-c11b4fc96eda.png |
| faces-type1-three_quarter-v2.png | exec-78f50624-ff6a-4e59-b9ab-cf02118680e6.png |
| faces-type1-defiant-v2.png | exec-eaa35ea5-db97-4eb3-abd7-afc2a6e9c048.png |
| faces-type2-front-v2.png | exec-92576eb8-011d-45c2-ad6a-06cc658f27c6.png |
| faces-type2-three_quarter-v2.png | exec-3ec4dccd-f63c-4aa4-852b-b8926ed33c34.png |
| faces-type2-defiant-v2.png | exec-44234611-337d-45fc-838b-22cf3e6eff68.png |

Source directory: C:/Users/nurdo/.codex/generated_images/01a092aa-7ec7-7630-8254-1e800b07e48e/

References: characters-reimagined-illustrated.png used for the male-front painterly style; accepted male-front used to preserve identity in male poses and style in female-front; accepted female-front used to preserve identity in female poses. Initial RGB checkerboard outputs were rejected; the final background-extraction prompt below yielded actual alpha. Old faces.png was tried once as a transparent reference but that output was rejected too. No discarded RGB output is referenced by project code.

## Exact accepted generation prompt set

### basePrompt

Use case: stylized-concept. Create production transparent PNG game sprite atlas 1024x1024 with exactly FOUR isolated bald adult MALE heads and short necks, in a regular 2 by 2 grid. Completely transparent alpha background, no checkerboard pixels. Painted gothic coastal village portrait art, bold textured gouache brushwork and expressive believable adult faces, matching the attached ensemble reference's painterly style (reference for style only; no clothes/background). Warm medium muted ochre complexion, uniform upper-left lighting, clean readable contour. All heads FRONT FACING, neutral but characterful expression. Top-left harbor: sturdy square jaw, thick brows, slightly crooked nose, grounded man aged 35. Top-right weathered: aged 60, lined forehead, strong older face. Bottom-left keen: angular slim face and sharp gaze, man aged 30. Bottom-right round: broad round cheeks, kindly subtle smile, man aged 40. Each head includes scalp, ears, face and short neck ONLY; no shoulder, chest, outfit, jewelry, facial hair, hair, hat, hand, prop, text. Identical vertical scale and matching neck baseline in every cell: head top at cell y=65, chin about y=365, neck bottom y=425; head centered cell x=256, maximal width about 260px. 512x512 cells. Generous transparent gutter. Anatomically normal, neither cartoon baby nor glamour fashion. These are modular heads for fitting clothes later. Make all four distinctly masculine.

### maleQuarterPrompt

Use case: identity-preserve. Edit this four-head sprite atlas to show the SAME FOUR BALD MEN in a three-quarter head pose: each face turned toward VIEWER RIGHT about 20 degrees, their eyes glance toward viewer. Redraw anatomical perspective (nose pointing right, right-side ear less visible), not rotating or tilting a frontal sprite. Keep their individual faces and ages, painted brushstroke style, warm skin, identical cell positions, head scale, crown height, neck-bottom landmark, uniform upper-left lighting. No clothing, shoulders, hair, facial hair, hats, jewelry, props or text. Keep actual transparent alpha background, output RGBA PNG. Four isolated head-and-short-neck sprites only, 2x2 grid. No painted checkerboard.

### maleDefiantPrompt

Use case: identity-preserve. Edit this four-head sprite atlas to show the SAME FOUR BALD MEN in a DEFIANT portrait head pose: each head turned toward VIEWER LEFT about 15 degrees, chin raised slightly, eyebrows expressive, sly confident half smile. Redraw anatomical perspective (nose pointing left, left-side ear less visible), not rotating a frontal sprite. Keep their individual identities and ages, painted brushstroke style, warm skin, identical cell positions, head scale, crown height, neck-bottom landmark, uniform upper-left lighting. No clothing, shoulders, hair, facial hair, hats, jewelry, props or text. Keep actual transparent alpha background, output RGBA PNG. Four isolated head-and-short-neck sprites only, 2x2 grid. No painted checkerboard.

### femaleFrontPrompt

Use case: stylized-concept. Create a companion FEMALE head atlas matching this male atlas's painterly gothic coastal-village game art, head scale, warm muted medium ochre complexion, and 2x2 cell layout. Four bald ADULT WOMEN front facing, short neck only, no shoulders/chest/outfits/hair/jewelry. Top-left harbor: grounded sturdy woman age35, broad cheekbones, strong nose, calm confident gaze. Top-right weathered: older woman age60, lined skin, expressive wise eyes, mature natural features. Bottom-left keen: angular narrow face adultwoman age30, sharp gaze, defined cheekbones. Bottom-right round: broad full cheeks womanage40, kindly subtle smile. Clearly feminine facial anatomy, realistic adult proportions and natural skin texture, no pin-up/glamour/makeup/eyeliner, no childlike features. Keep each head centered its cell, crown height and neck baseline consistent with reference, identicalupper-left lighting. Transparentbackground output RGBA PNG with actualalpha, no paintedcheckerboard. Bold textured gouache brushwork. No text, no facialhair, no props.

### femaleQuarterPrompt

Use case: identity-preserve. Edit this four-head sprite atlas to show the SAME FOUR BALD WOMEN in a three-quarter head pose: each face turned toward VIEWER RIGHT about 20 degrees, their eyes glance toward viewer. Redraw anatomical perspective (nose pointing right, right-side ear less visible), not rotating or tilting a frontal sprite. Keep their individual faces and ages, painted brushstroke style, warm skin, identical cell positions, head scale, crown height, neck-bottom landmark, uniform upper-left lighting. Clearly feminine natural adult facial anatomy. No clothing, shoulders, hair, facial hair, hats, jewelry, props or text. Keep actual transparent alpha background, output RGBA PNG. Four isolated head-and-short-neck sprites only, 2x2 grid. No painted checkerboard.

### femaleDefiantPrompt

Use case: identity-preserve. Edit this four-head sprite atlas to show the SAME FOUR BALD WOMEN in a DEFIANT portrait head pose: each head turned toward VIEWER LEFT about 15 degrees, chin raised slightly, eyebrows expressive, sly confident half smile. Redraw anatomical perspective (nose pointing left, left-side ear less visible), not rotating a frontal sprite. Keep their individual identities and ages, painted brushstroke style, warm skin, identical cell positions, head scale, crown height, neck-bottom landmark, uniform upper-left lighting. Clearly feminine natural adult facial anatomy. No clothing, shoulders, hair, facial hair, hats, jewelry, props or text. Keep actual transparent alpha background, output RGBA PNG. Four isolated head-and-short-neck sprites only, 2x2 grid. No painted checkerboard.

### extractionPrompt

Use case: background-extraction. Remove the painted checkerboard from this four-head atlas, including all gaps. Replace it with actual alpha transparency. Preserve every painted head pixel, size, position, angle, exact cell layout and dimensions. DO NOT generate a checkerboard or colored background. Output RGBA PNG with transparent empty areas, not RGB PNG.

## Female anatomy refinement

After assembly review, all three type2 sheets were refined to reduce the masculine jaw, brow ridge and neck mass while keeping adults and the weathered woman's age. Final filenames unchanged because they are new uncommitted assets for this task. Updated metadata is in resources/js/lib/creatorPoseArt.ts. Accepted replacement sources:

- front: exec-3d5d617d-8821-443d-8f8f-5019cbe3bf50.png
- three_quarter: exec-f38f7fdb-fb6f-4a00-a995-eaac326339b5.png
- defiant: exec-fdeb1cfa-7330-45eb-b07f-4830d83c708c.png

Each is1254x1254RGBAwithalpha0..255. Builtin imagegen only. Exact refinement prompt below with pose-specific appended sentence (front straight frontal, three_quarter viewer RIGHT20degrees, defiant viewer LEFT15degrees withraisedchin/confidence):

Use case: precise-object-edit. Refine ONLY the anatomy of the four adult WOMEN'S heads in this sprite atlas so every face reads clearly female at small portrait size. Current heavy squared jaws, thick brow ridges and wide muscular necks look too masculine. Give all four women narrower gently rounded jaws, smaller rounded chins, less projecting brow bones, finer naturally shaped eyebrows, slightly smaller noses and ears, slender natural necks, softer facial planes. Preserve individuality: top-left grounded sturdy adult woman age35, top-right clearly ELDERLY WOMAN age65 with wrinkles and age-softened jaw (do not rejuvenate), bottom-left angular keen adult woman age30, bottom-right full-cheeked round-faced woman age40. Feminine facial structure, not makeup, beauty retouching, glamour or pinup. Keep exact existing head pose, painted coarse gouache brushwork, complexion, upper-left lighting, cell positions, scalp top, eye-line and neck baseline. Keep original overall head bounding rectangle approximately so existing hair/clothes remain fitted. Short necks only, no shoulders/clothing/hair/jewelry/text. Keep actual alpha transparency outside heads; output RGBA PNG, no painted checkerboard. 

Front and quarter used the earlier exact extraction prompt. Defiant needed this final extraction prompt:

Remove background. Deliver these four female head sprites as isolated cutouts with a TRANSPARENT BACKGROUND. Erase all gray and white squares completely. Keep heads intact. Return a true RGBA PNG with alpha channel. All space between and around the heads must have zero opacity. No checkerboard should appear in the image pixels.



# Outfit art — body types and poses

Built-in imagegen generation only; no CLI/API, programmatic raster edits or alpha removal. Six final RGBA PNGs copied unchanged from the generator into `public/assets/chanting/creator/`. Each PNG is 1254×1254 with PNG color type 6. Four garments per 2×2 atlas: mariner, scholar, waistcoat, ritual. Final outputs use true alpha outside painted clothing; checkerboard RGB edit trials were rejected.

Metadata in `resources/js/lib/creatorPoseArt.ts` uses absolute atlas crop coordinates and absolute neck-opening centers. Neck anchors are manually estimated from inspected art and intended for final runtime alignment. All poses have covered shirts and no skin. Front stance uses relaxed arms; three-quarter faces viewer right; defiant faces viewer left with folded arms and full gloves.

## type1/front

Generated images are saved to C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f as C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f\exec-94381889-1ef2-4d50-b271-f282814c8e33.png by default.
If you need to use a generated image at another path, copy it and leave the original in place unless the user explicitly asks you to delete it.
The generated image is already displayed to the user. There is no need to render it in the final response as a Markdown image or file link.

Final prompt:

Use case: stylized-concept.
Asset type: transparent game character creator clothing sprite atlas, square 1536 by 1536.
Create FOUR separate headless adult masculine clothed upper-body bust sprites, arranged in a precise 2x2 grid of equal cells. These are garment layers for a modular portrait game. Actual transparent alpha background, no checkerboard painted into image. All four bodies FACE STRAIGHT FORWARD with relaxed arms naturally extending below the bottom edge of the bust, strong realistic adult male shoulders.
Cell order: top left weathered naval mariner double-breasted pea coat with aged brass buttons and broad lapels; top right scholarly long coat with small buttoned stand collar and scholarly brass clasp; bottom left tailored waistcoat over a modest fully buttoned long-sleeve shirt; bottom right layered ritual cloak with closed high-neck inner robe and subtle stitched occult pattern.
Art style: expressive hand-painted gothic village storybook portrait, thick confident dark ink contours, visible gouache brush strokes, chunky planes of light and shadow, warm brass and muted grey green fabric, slightly worn antique cloth. NOT vector, NOT photoreal, NOT cute doll. Neutral grey/desaturated sage fabric suitable for runtime palette tinting.
Exact composition: equal 768x768 cells. Each sprite inside its own cell with at least 80px transparent side margin and 80px bottom margin, centered x at cell center; neckline at y=120 within each cell, shoulders begin near y=140. Bust from shoulder to below waist filling about 590px width by 550px height. Match the body silhouette and collar/neck opening position across all four clothing choices. Upper chest fully covered with undershirt under every coat, so there is no deep empty V-shaped gap.
CRITICAL: GARMENTS ONLY. No heads, faces, hair, hats, skin, neck stumps, bare hands, mannequins, hangers, labels or props. Empty transparent small neck opening for a separately composited head with neck; no visible naked chest. No hollow disconnected cuffs: arms continue naturally into lower crop. Clean alpha outside garment; keep all four sprites separate, no touching or overlap. No text, no background, no frames.

## type1/three_quarter

Generated images are saved to C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f as C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f\exec-1102ca0d-c8f3-4cdd-a803-8f40258fdd49.png by default.
If you need to use a generated image at another path, copy it and leave the original in place unless the user explicitly asks you to delete it.
The generated image is already displayed to the user. There is no need to render it in the final response as a Markdown image or file link.

Final prompt:

Use case: stylized-concept.
Asset type: transparent game character creator clothing sprite atlas, square 1536 by 1536.
Create FOUR separate headless adult masculine clothed upper-body bust sprites, arranged in a precise 2x2 grid of equal cells. These are garment layers for a modular portrait game. Actual transparent alpha background, no checkerboard painted into image. All four bodies stand in THREE-QUARTER VIEW, turned 25 degrees toward viewer RIGHT with closer left shoulder and receding right shoulder. Distinctly asymmetric coat lapels and sleeves, relaxed arms, strong realistic male shoulders.
Cell order: top left weathered naval mariner double-breasted pea coat with aged brass buttons and broad lapels; top right scholarly long coat with small buttoned stand collar and scholarly brass clasp; bottom left tailored waistcoat over a modest fully buttoned long-sleeve shirt; bottom right layered ritual cloak with closed high-neck inner robe and subtle stitched occult pattern.
Art style: expressive hand-painted gothic village storybook portrait, thick confident dark ink contours, visible gouache brush strokes, chunky planes of light and shadow, warm brass and muted grey green fabric, slightly worn antique cloth. NOT vector, NOT photoreal, NOT cute doll. Neutral grey/desaturated sage fabric suitable for runtime palette tinting.
Exact composition: equal 768x768 cells. Each sprite inside its own cell with at least 80px transparent side margin and 80px bottom margin, centered x at cell center; neckline at y=120 within each cell, shoulders begin near y=140. Bust from shoulder to below waist filling about 590px width by 550px height. Match the body silhouette and collar/neck opening position across all four clothing choices. Upper chest fully covered with undershirt under every coat, so there is no deep empty V-shaped gap.
CRITICAL: GARMENTS ONLY. No heads, faces, hair, hats, skin, neck stumps, bare hands, mannequins, hangers, labels or props. Empty transparent small neck opening for a separately composited head with neck; no visible naked chest. No hollow disconnected cuffs: arms continue naturally into lower crop. Clean alpha outside garment; keep all four sprites separate, no touching or overlap. No text, no background, no frames.

## type1/defiant

Generated images are saved to C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f as C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f\exec-4b9404e2-124d-4a83-8b06-df1f6f3871ba.png by default.
If you need to use a generated image at another path, copy it and leave the original in place unless the user explicitly asks you to delete it.
The generated image is already displayed to the user. There is no need to render it in the final response as a Markdown image or file link.

Final prompt:

Use case: stylized-concept.
Asset type: transparent game character creator clothing sprite atlas, square 1536 by 1536.
Create FOUR separate headless adult masculine clothed upper-body bust sprites, arranged in a precise 2x2 grid of equal cells. These are garment layers for a modular portrait game. Actual transparent alpha background, no checkerboard painted into image. All four bodies stand in a DEFIANT POSE, torso turned 20 degrees toward viewer LEFT, closer right shoulder and receding left shoulder, BOTH ARMS naturally crossed across their chests, full dark leather gloves covering both hands. Clearly visible realistic elbows and forearms, strong adult male shoulders.
Cell order: top left weathered naval mariner double-breasted pea coat with aged brass buttons and broad lapels; top right scholarly long coat with small buttoned stand collar and scholarly brass clasp; bottom left tailored waistcoat over a modest fully buttoned long-sleeve shirt; bottom right layered ritual cloak with closed high-neck inner robe and subtle stitched occult pattern.
Art style: expressive hand-painted gothic village storybook portrait, thick confident dark ink contours, visible gouache brush strokes, chunky planes of light and shadow, warm brass and muted grey green fabric, slightly worn antique cloth. NOT vector, NOT photoreal, NOT cute doll. Neutral grey/desaturated sage fabric suitable for runtime palette tinting.
Exact composition: equal 768x768 cells. Each sprite inside its own cell with at least 80px transparent side margin and 80px bottom margin, centered x at cell center; neckline at y=120 within each cell, shoulders begin near y=140. Bust from shoulder to below waist filling about 590px width by 550px height. Match the body silhouette and collar/neck opening position across all four clothing choices. Upper chest fully covered with undershirt under every coat, so there is no deep empty V-shaped gap.
CRITICAL: GARMENTS ONLY. No heads, faces, hair, hats, skin, neck stumps, bare hands, mannequins, hangers, labels or props. Empty transparent small neck opening for a separately composited head with neck; no visible naked chest. Both arms folded naturally, wearing leather gloves: absolutely no bare skin. Clean alpha outside garment; keep all four sprites separate, no touching or overlap. No text, no background, no frames.

## type2/front

Generated images are saved to C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f as C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f\exec-2514bd5d-5439-4594-b0c1-b2fb84d28f0d.png by default.
If you need to use a generated image at another path, copy it and leave the original in place unless the user explicitly asks you to delete it.
The generated image is already displayed to the user. There is no need to render it in the final response as a Markdown image or file link.

Final prompt:

Use case: stylized-concept.
Asset type: transparent game character creator clothing sprite atlas, square 1536 by 1536.
Create FOUR separate headless adult feminine clothed upper-body bust sprites, arranged in a precise 2x2 grid of equal cells. These are garment layers for a modular portrait game. Actual transparent alpha background, no checkerboard painted into image. All four bodies FACE STRAIGHT FORWARD with relaxed arms naturally extending below the bottom edge of the bust, moderately narrower realistic adult female shoulders, softly tailored feminine bust and waist, fully covered chest; adult woman, not exaggerated hourglass.
Cell order: top left weathered naval mariner double-breasted pea coat with aged brass buttons and broad lapels; top right scholarly long coat with small buttoned stand collar and scholarly brass clasp; bottom left tailored waistcoat over a modest fully buttoned long-sleeve shirt; bottom right layered ritual cloak with closed high-neck inner robe and subtle stitched occult pattern.
Art style: expressive hand-painted gothic village storybook portrait, thick confident dark ink contours, visible gouache brush strokes, chunky planes of light and shadow, warm brass and muted grey green fabric, slightly worn antique cloth. NOT vector, NOT photoreal, NOT cute doll. Neutral grey/desaturated sage fabric suitable for runtime palette tinting.
Exact composition: equal 768x768 cells. Each sprite inside its own cell with at least 80px transparent side margin and 80px bottom margin, centered x at cell center; neckline at y=120 within each cell, shoulders begin near y=140. Bust from shoulder to below waist filling about 590px width by 550px height. Match the body silhouette and collar/neck opening position across all four clothing choices. Upper chest fully covered with undershirt under every coat, so there is no deep empty V-shaped gap.
CRITICAL: GARMENTS ONLY. No heads, faces, hair, hats, skin, neck stumps, bare hands, mannequins, hangers, labels or props. Empty transparent small neck opening for a separately composited head with neck; no visible naked chest. No hollow disconnected cuffs: arms continue naturally into lower crop. Clean alpha outside garment; keep all four sprites separate, no touching or overlap. No text, no background, no frames.

## type2/three_quarter

Generated images are saved to C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f as C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f\exec-acf01a38-14a5-49a5-a01d-f32d38a63f5e.png by default.
If you need to use a generated image at another path, copy it and leave the original in place unless the user explicitly asks you to delete it.
The generated image is already displayed to the user. There is no need to render it in the final response as a Markdown image or file link.

Final prompt:

Use case: stylized-concept.
Asset type: transparent game character creator clothing sprite atlas, square 1536 by 1536.
Create FOUR separate headless adult feminine clothed upper-body bust sprites, arranged in a precise 2x2 grid of equal cells. These are garment layers for a modular portrait game. Actual transparent alpha background, no checkerboard painted into image. All four bodies stand in THREE-QUARTER VIEW, turned 25 degrees toward viewer RIGHT with closer left shoulder and receding right shoulder. Distinctly asymmetric coat lapels and sleeves, relaxed arms, moderately narrower realistic adult female shoulders, softly tailored feminine bust and waist, fully covered chest; adult woman, not exaggerated hourglass.
Cell order: top left weathered naval mariner double-breasted pea coat with aged brass buttons and broad lapels; top right scholarly long coat with small buttoned stand collar and scholarly brass clasp; bottom left tailored waistcoat over a modest fully buttoned long-sleeve shirt; bottom right layered ritual cloak with closed high-neck inner robe and subtle stitched occult pattern.
Art style: expressive hand-painted gothic village storybook portrait, thick confident dark ink contours, visible gouache brush strokes, chunky planes of light and shadow, warm brass and muted grey green fabric, slightly worn antique cloth. NOT vector, NOT photoreal, NOT cute doll. Neutral grey/desaturated sage fabric suitable for runtime palette tinting.
Exact composition: equal 768x768 cells. Each sprite inside its own cell with at least 80px transparent side margin and 80px bottom margin, centered x at cell center; neckline at y=120 within each cell, shoulders begin near y=140. Bust from shoulder to below waist filling about 590px width by 550px height. Match the body silhouette and collar/neck opening position across all four clothing choices. Upper chest fully covered with undershirt under every coat, so there is no deep empty V-shaped gap.
CRITICAL: GARMENTS ONLY. No heads, faces, hair, hats, skin, neck stumps, bare hands, mannequins, hangers, labels or props. Empty transparent small neck opening for a separately composited head with neck; no visible naked chest. No hollow disconnected cuffs: arms continue naturally into lower crop. Clean alpha outside garment; keep all four sprites separate, no touching or overlap. No text, no background, no frames.

## type2/defiant

Generated images are saved to C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f as C:\Users\nurdo\.codex\generated_images\01a092aa-5226-7852-a327-207c14a2ff3f\exec-eba7db20-a647-4879-97e8-8f07d664ba6b.png by default.
If you need to use a generated image at another path, copy it and leave the original in place unless the user explicitly asks you to delete it.
The generated image is already displayed to the user. There is no need to render it in the final response as a Markdown image or file link.

Final prompt:

Use case: stylized-concept.
Asset type: transparent game character creator clothing sprite atlas, square 1536 by 1536.
Create FOUR separate headless adult feminine clothed upper-body bust sprites, arranged in a precise 2x2 grid of equal cells. These are garment layers for a modular portrait game. Actual transparent alpha background, no checkerboard painted into image. All four bodies stand in a DEFIANT POSE, torso turned 20 degrees toward viewer LEFT, closer right shoulder and receding left shoulder, BOTH ARMS naturally crossed across their chests, full dark leather gloves covering both hands. Clearly visible realistic elbows and forearms, moderately narrower realistic adult female shoulders, softly tailored feminine bust and waist, fully covered chest; adult woman, not exaggerated hourglass.
Cell order: top left weathered naval mariner double-breasted pea coat with aged brass buttons and broad lapels; top right scholarly long coat with small buttoned stand collar and scholarly brass clasp; bottom left tailored waistcoat over a modest fully buttoned long-sleeve shirt; bottom right layered ritual cloak with closed high-neck inner robe and subtle stitched occult pattern.
Art style: expressive hand-painted gothic village storybook portrait, thick confident dark ink contours, visible gouache brush strokes, chunky planes of light and shadow, warm brass and muted grey green fabric, slightly worn antique cloth. NOT vector, NOT photoreal, NOT cute doll. Neutral grey/desaturated sage fabric suitable for runtime palette tinting.
Exact composition: equal 768x768 cells. Each sprite inside its own cell with at least 80px transparent side margin and 80px bottom margin, centered x at cell center; neckline at y=120 within each cell, shoulders begin near y=140. Bust from shoulder to below waist filling about 590px width by 550px height. Match the body silhouette and collar/neck opening position across all four clothing choices. Upper chest fully covered with undershirt under every coat, so there is no deep empty V-shaped gap.
CRITICAL: GARMENTS ONLY. No heads, faces, hair, hats, skin, neck stumps, bare hands, mannequins, hangers, labels or props. Empty transparent small neck opening for a separately composited head with neck; no visible naked chest. Both arms folded naturally, wearing leather gloves: absolutely no bare skin. Clean alpha outside garment; keep all four sprites separate, no touching or overlap. No text, no background, no frames.


