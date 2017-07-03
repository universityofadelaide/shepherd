import Vue from 'vue';
import Axios from 'axios';
// This is a vue js component.
import VueTable from './Table.vue';

// Make available to all child components.
window.Axios = Axios;

var vue_table = new Vue({
  el: '#vue_table__app',
  render: h => h(VueTable)
});
