import Focus from '@alpinejs/focus';
import Collapse from '@alpinejs/collapse';

document.addEventListener('alpine:init', () => {
    Alpine.plugin(Focus);
    Alpine.plugin(Collapse);
});
