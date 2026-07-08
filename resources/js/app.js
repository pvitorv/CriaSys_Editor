import './bootstrap';
import Alpine from 'alpinejs';
import sort from '@alpinejs/sort';

Alpine.plugin(sort);
window.Alpine = Alpine;

import './alerts';
import './dashboard';
import './editor';

Alpine.start();
