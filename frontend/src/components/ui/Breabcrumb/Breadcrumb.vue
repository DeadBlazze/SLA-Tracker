<template lang="pug">
nav.breadcrumbs(aria-label="Хлебные крошки" v-if="breadcrumbs.length > 1")
  ol.breadcrumbs__list
    li.breadcrumbs__item(
      v-for="(crumb, index) in breadcrumbs" 
      :key="crumb.path"
    )
      //- Последний элемент — некликабельный текст
      span.breadcrumbs__current(
        v-if="index === breadcrumbs.length - 1" 
        aria-current="page"
      ) {{ crumb.title }}

      //- Промежуточные элементы — ссылки
      router-link.breadcrumbs__link(
        v-else 
        :to="crumb.path"
      ) {{ crumb.title }}

      //- Разделитель
      span.breadcrumbs__separator(v-if="index < breadcrumbs.length - 1") /
</template>
<script>
export default {
  name: "AppBreadcrumbs",
  computed: {
    breadcrumbs() {
      const matched = this.$route.matched.filter(
        (record) => record.meta && record.meta.breadcrumb
      );

      const crumbs = matched.map((record) => {
        const resolvedPath = this.resolvePath(record.path);
        
        const title = typeof record.meta.breadcrumb === "function"
          ? record.meta.breadcrumb(this.$route)
          : record.meta.breadcrumb;

        return {
          title,
          path: resolvedPath
        };
      });

      const hasHome = crumbs.some((crumb) => crumb.path === "/");
      if (!hasHome && this.$route.path !== "/") {
        crumbs.unshift({
          title: "Главная",
          path: "/"
        });
      }

      return crumbs;
    }
  },
  methods: {
    resolvePath(pathPattern) {
      let resolved = pathPattern;
      for (const [key, value] of Object.entries(this.$route.params)) {
        resolved = resolved.replace(`:${key}`, value);
      }
      return resolved || "/";
    }
  }
};
</script>

<style lang="scss" scoped>
@use './breadcrumb' as *;
</style>