document.addEventListener("DOMContentLoaded", function() {
    const searchForm = document.getElementById('searchSalesForm');
    const searchQuery = document.getElementById('searchQuery');
    const salesTableBody = document.getElementById('salesTable').querySelector('tbody');
    let rows = Array.from(salesTableBody.getElementsByTagName('tr'));

    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const query = searchQuery.value.trim().toLowerCase();

        if (query === '') {
            resetTable();
        } else {
            filterTable(query);
        }
    });

    function filterTable(query) {
        rows.forEach(row => {
            const cells = Array.from(row.getElementsByTagName('td'));
            const match = cells.some(cell => cell.innerText.toLowerCase().includes(query));
            row.style.display = match ? '' : 'none';
        });
    }

    function resetTable() {
        rows.forEach(row => {
            row.style.display = '';
        });
    }
});