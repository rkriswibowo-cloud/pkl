<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Lupa Kata Sandi - Sistem Logbook PKL</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com "></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-8 space-y-6">

        <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-800">🔑 Lupa Kata Sandi</h2>
            <p class="mt-2 text-sm text-gray-500">Masukkan email atau username Anda</p>
        </div>

        <form action="#" method="post" class="space-y-5">
            <div>
                <label for="email_or_username" class="block text-sm font-medium text-gray-700 mb-1">Email atau Username</label>
                <input type="text" name="email_or_username" id="email_or_username" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 outline-none transition duration-200">
            </div>

            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                Kirim Instruksi Reset
            </button>
        </form>

        <div class="text-center mt-4">
            <a href="index.php" class="text-sm text-blue-600 hover:text-blue-800 hover:underline transition duration-200">
                ← Kembali ke login
            </a>
        </div>

    </div>

</body>
</html>