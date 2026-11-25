# ESY.PL Password Generator

Password generator. The page lives at `https://esy.pl/password-generator/`.

Passwords are created on the server in PHP 8.5 and returned to the browser in one request. The browser only draws the form and the entropy bar.

## How it works

There are four modes. **Custom** unlocks the controls: character groups, length from 8 to 128, count from 1 to 25, removal of lookalike characters (`0`, `O`, `o`, `1`, `l`, `I`, `i`), and an option to start and end the password with a letter. **Strong**, **Very strong**, and **Quantum secure** are fixed answers. Character groups and length stay locked, and the server ignores those settings. After consent, the number of passwords can still be chosen, from 1 to 25. Without consent that control stays locked on a preset.

| Mode | Password | Entropy |
|---|---|---|
| Strong | 16 characters from the generator's full ASCII alphabet | about 103 bits |
| Very strong | 20 characters, same alphabet | about 128 bits |
| Quantum secure | 41 characters, same alphabet; the first and last are letters without lookalikes | about 261 bits |

Randomness comes from `Random\Engine\Secure`. Characters are drawn with `getBytesFromString()`.

The bar shows the entropy of the current setup, in bits, on a scale that fills at 256. The number of passwords does not increase the entropy of one password. A separate field estimates a password you type in. That password is not stored, logged, or sent to analytics. A hit on a common password (including leet lookalikes), a keyboard walk, a run such as `1234` or `abcd`, or four identical characters in a row caps the verdict at "weak". Generated passwords that hit the same patterns are drawn again, up to 32 times.

Languages: English, Polish, French, Spanish, and Simplified Chinese. With no matching `Accept-Language` header, the page starts in English.

On the first visit a banner explains Google Analytics, Google AdSense, and saving settings for 7 days. Agreeing turns those three on and unlocks Very strong and Quantum secure, which then becomes the selected mode. Declining leaves Custom and Strong only. The choice itself is kept in a necessary cookie so the question is not repeated. After a choice is stored, a “Change cookie preferences” link clears it and shows the banner again. The settings cookie is separate and is written only after agreement.

A generate or check request is accepted only from this site: a token from the page, a custom header, and an origin check. The limit is per session, not per IP address.

## Run it

From `src/`, with PHP 8.5 on `PATH`:

```
npm install
npm run build
npm run check
```

`npm install` is only needed once, or after dependencies change. It may warn that install scripts for `@parcel/watcher` and `esbuild` are blocked by `allowScripts`. The watcher is only used by `sass --watch`, which this project does not run. The esbuild package still minifies during `npm run build` when that command prints `Done`. Both warnings can be ignored. Do not approve the scripts unless a later build says the esbuild binary is missing.

`npm run build` writes the deployable site to `password-generator/`. CSS and JavaScript in `assets/` are minified; the readable sources stay in `src/`. `npm run check` should print `ok`.

Upload `password-generator/` by FTP. Do not upload `src/`, `node_modules`, `old/`, or `hasla/`. On the server, `hasla/` holds only a 301 redirect to `https://esy.pl/password-generator/`. That directory is not part of this public repository.

`old/` is the previous client-side generator and is kept locally as a reference. It is not published here.
