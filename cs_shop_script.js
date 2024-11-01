window.addEventListener('DOMContentLoaded', () => {
    const boxes = document.querySelectorAll(".cs_shop_ani");
    shows(boxes);
    click_checked();
});

async function shows(arr) {
    for (const item of arr) {
        item.classList.add('cs_shop_animate');
        await delays(100);
    }
}

function delays(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function click_checked() {
    let chbox = document.getElementById('check_job_vorlage');
    let vorlagen = document.getElementById('job_vorlagen');
    if (chbox) {
        if (chbox.checked) {
            vorlagen.style.display = 'table-row';
        } else {
            vorlagen.style.display = 'none';
        }
    }
}

