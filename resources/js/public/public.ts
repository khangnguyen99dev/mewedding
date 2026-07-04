/**
 * Entry for clean Blade templates. Boots shared interactivity once DOM is ready.
 */
import { bootPublic } from './core';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootPublic);
} else {
    bootPublic();
}
