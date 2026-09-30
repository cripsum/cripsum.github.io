<?php
/*
 * Dimensioni DECOMPRESSE dei file di ogni build (dati, wasm, framework),
 * per nome file. subway.js le passa al loader Unity quando il server non
 * manda Content-Length (Cloudflare ricomprime al volo): senza, il loader
 * del 2019 va in errore a ogni evento di download.
 * Generato da scripts/subway/build-sizes.js a partire da subway-builds-br.
 */

return [
    'bangkok' => ['bangkok.alt.data.unityweb' => 86723068, 'bangkok.alt.wasm.code.unityweb' => 21970088, 'bangkok.alt.wasm.framework.unityweb' => 426008],
    'barcelona' => ['barcelona.alt.data.unityweb' => 26686614, 'wasm_code_shared_2.unityweb' => 26475089, 'wasm_framework.unityweb' => 521663, 'barcelona.training.data.unityweb' => 41280678, 'barcelona.training3rows.data.unityweb' => 41032866, 'barcelona.trainingobstacles.data.unityweb' => 41281110],
    'beijing' => ['beijing.alt.data.unityweb' => 29477323, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663, 'beijing.training.data.unityweb' => 50406580, 'beijing.training3rows.data.unityweb' => 50158768, 'beijing.trainingobstacles.data.unityweb' => 50407012],
    'berlin' => ['berlin.alt.data.unityweb' => 28855705, 'berlin.wasm.code.unityweb' => 26681155, 'wasm_framework.unityweb' => 521663],
    'buenosaires' => ['buenosaires.alt.data.unityweb' => 28926246, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663, 'buenosaires.training.data.unityweb' => 48289130, 'buenosaires.training3rows.data.unityweb' => 48041328, 'buenosaires.trainingobstacles.data.unityweb' => 48289562],
    'cairo' => ['cairo.alt.data.unityweb' => 45431110, 'cairo.alt.wasm.code.unityweb' => 26477333, 'cairo.alt.wasm.framework.unityweb' => 540326],
    'havana' => ['havana.alt.data.unityweb' => 27994292, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663, 'havana.training.data.unityweb' => 46454140, 'havana.training3rows.data.unityweb' => 46206338, 'havana.trainingobstacles.data.unityweb' => 46454572],
    'hongkong' => ['hongkong.alt.data.unityweb' => 60555975, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663],
    'houston' => ['houston.alt.data.unityweb' => 27813232, 'houston.wasm.code.unityweb' => 26679193, 'wasm_framework.unityweb' => 521663],
    'iceland' => ['iceland.alt.data.unityweb' => 27865882, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663, 'iceland.training.data.unityweb' => 46549158, 'iceland.training3rows.data.unityweb' => 46301346, 'iceland.trainingobstacles.data.unityweb' => 46549590],
    'london' => ['london.alt.data.unityweb' => 29146990, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663, 'london.training.data.unityweb' => 51500366, 'london.training3rows.data.unityweb' => 51252554, 'london.trainingobstacles.data.unityweb' => 51500798],
    'mexico' => ['mexico.alt.data.unityweb' => 28424271, 'wasm_code_shared_2.unityweb' => 26475089, 'wasm_framework.unityweb' => 521663, 'mexico.training.data.unityweb' => 49147318, 'mexico.training3rows.data.unityweb' => 48899506, 'mexico.trainingobstacles.data.unityweb' => 49147750],
    'miami' => ['miami.alt.data.unityweb' => 34189276, 'miami.wasm.code.unityweb' => 21971555, 'wasm_framework.unityweb' => 425488, 'miami.training.data.unityweb' => 61232150, 'miami.training3rows.data.unityweb' => 61232150, 'miami.trainingobstacles.data.unityweb' => 61232150],
    'monaco' => ['monaco.alt.data.unityweb' => 27819026, 'wasm_code_shared_3.unityweb' => 25765359, 'wasm_framework.unityweb' => 521663],
    'moscow' => ['moscow.alt.data.unityweb' => 50711641, 'moscow.alt.wasm.code.unityweb' => 26471806, 'moscow.alt.wasm.framework.unityweb' => 461949],
    'neworleans' => ['neworleans.alt.data.unityweb' => 28801916, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663, 'neworleans.training.data.unityweb' => 49806899, 'neworleans.training3rows.data.unityweb' => 49559087, 'neworleans.trainingobstacles.data.unityweb' => 49807331],
    'newyork' => ['newyork.alt.data.unityweb' => 53381551, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663],
    'paris' => ['paris.alt.data.unityweb' => 46861366, 'paris.alt.wasm.code.unityweb' => 26477333, 'paris.alt.wasm.framework.unityweb' => 540326],
    'rio' => ['rio.alt.data.unityweb' => 58313864, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663],
    'saintpetersburg' => ['saintpetersburg.alt.data.unityweb' => 28267435, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663, 'saintpetersburg.training.data.unityweb' => 47248248, 'saintpetersburg.training3rows.data.unityweb' => 47000446, 'saintpetersburg.trainingobstacles.data.unityweb' => 47248690],
    'sanfrancisco' => ['sanfrancisco.alt.data.unityweb' => 27979216, 'wasm_code_shared_3.unityweb' => 25765359, 'wasm_framework.unityweb' => 521663],
    'tokyo' => ['tokyo.alt.data.unityweb' => 55340654, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663],
    'venice' => ['venice.alt.data.unityweb' => 58719414, 'wasm_code_shared.unityweb' => 26477341, 'wasm_framework.unityweb' => 521663],
    'winterholiday' => ['winterholiday.alt.data.unityweb' => 29434207, 'winterholiday.wasm.code.unityweb' => 26475147, 'wasm_framework.unityweb' => 521663, 'winterholiday.training.data.unityweb' => 51261314, 'winterholiday.training3rows.data.unityweb' => 51013502, 'winterholiday.trainingobstacles.data.unityweb' => 51261746],
    'zurich' => ['zurich.alt.data.unityweb' => 28988830, 'zurich.wasm.code.unityweb' => 25767131, 'wasm_framework.unityweb' => 521663],
];
