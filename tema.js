const body = document.body;
const btn = document.getElementById('toggle');

// Ambil tema terakhir dari localStorage jika ada
if (localStorage.getItem('theme') === 'night') {
	body.classList.add('night');
}

btn.addEventListener('click', () => {
	body.classList.toggle('night');
	// Simpan status tema di localStorage
	if (body.classList.contains('night')) {
		localStorage.setItem('theme', 'night');
	} else {
		localStorage.setItem('theme', 'light');
	}
});
