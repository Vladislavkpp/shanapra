<?php
/**
 * @var $md
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

//use vendor\vodovra\Dbs;
use vendor\vodovra\Page;
use vendor\vodovra\View;


Page::SetTitle('User Page');
View::Add('<link rel="stylesheet" href="/assets/css/shanas.css">');
View::Add('<div class="app loading">
    <div class="header">
        <div class="header-wrapper">
            <div class="header-container">
                <div class="header-title">
                    <div class="header-title__logo">
                        <img src="/images/content/logo.svg" alt="logo">
                    </div>
                    <div class="header-title__text">
                        <p>Офіційна карта повітряних тривог України</p>
                        <span>map.ukrainealarm.com</span>
                    </div>
                </div>
                <div class="header-links">
                    <a href="https://ukrainealarm.com/" class="header-link">Застосунок Повітряна тривога</a>
                    <a href="https://ukrainealarm.com/" class="header-link">Контакти</a>
                    <a href="https://api.ukrainealarm.com/" class="header-link">Для розробників (API)</a>
                </div>
                <div class="header-hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </div>
    <div class="content">
        <div class="toolbar">
            <div class="theme">
                <span class="theme-title">
                    Тема
                </span>
                <div class="theme-switcher"></div>
            </div>
            <button class="statistics-button">Статистика</button>
        </div>');
View::Out();

