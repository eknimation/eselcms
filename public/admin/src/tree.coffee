Vue.component 'pages',
  template: '
    <ul class="nav bordered right-blue-gray-border-menu tree in-loading">
    <li v-for="page in pages" :class="{active:page.active}">
    <a v-if="page.folder" v-bind:href="page.url" v-on:click.prevent="enter(page)" > <i class="fa fa-folder-o fa-fw dark-hint" aria-hidden="true"></i>
{{page.name}}</a>
    <a v-else v-bind:href="\'pages/?page=\'+page.path" > <i class="fa fa-file-text-o fa-fw dark-hint" aria-hidden="true"></i>
 {{page.name}}</a>
    </li>
    </ul>'
  props:["pages"]
  methods:
    enter:(page)->
      $(this.$el).addClass "in-loading"
      this.$parent.$emit "openFolder",page