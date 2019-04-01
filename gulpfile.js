"use strict";
/*
 npm install gulp@4.0.0 gulp-build-bitrix-modul --save
 */

let gulp = require('gulp');
let build = require('gulp-build-bitrix-modul')({
    name: 'digitalwand.admin_helper'
});

// Сборка текущей версии модуля
gulp.task('release', build.release);

// Сборка текущей версии модуля для маркеплейса
gulp.task('last_version', build.last_version);

// Сборка обновления модуля (разница между последней и предпоследней версией по тегам git)
gulp.task('build_update', build.update);

// Дефолтная задача. Собирает все по очереди
gulp.task('default', gulp.series('release'));