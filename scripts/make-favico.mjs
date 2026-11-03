import svg_to_ico from 'svg-to-ico';

svg_to_ico({
    input_name: './resources/icon.svg',
    output_name: './public/icons/favicon.ico',
    sizes: [16, 24, 32, 64],
})
    .then(() => {
        console.log('file converted');
    })
    .catch((error) => {
        console.error(`file conversion failed: ${error}`);
    });
