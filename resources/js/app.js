import './bootstrap';
import Alpine from 'alpinejs';
import sort from '@alpinejs/sort';

Alpine.plugin(sort);
window.Alpine = Alpine;

import './alerts';
import './editor';

Alpine.start();
