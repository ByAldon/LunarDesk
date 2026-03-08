function startTinyToolbar() {

    tinymce.init({

        selector: "#editorjs .ce-paragraph",

        inline: true,
        menubar: false,

        toolbar:
        "undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | link table",

        plugins: [
            "lists",
            "link",
            "table"
        ],

        fixed_toolbar_container: "#tinymce-toolbar",

        content_style:
        "body { background: transparent; color: #e5e7eb; font-family: system-ui; }"

    });

}